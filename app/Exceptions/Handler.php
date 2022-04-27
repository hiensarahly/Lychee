<?php

namespace App\Exceptions;

use App\Contracts\HttpExceptionHandler;
use App\DTO\BacktraceRecord;
use App\Exceptions\Handlers\AccessDBDenied;
use App\Exceptions\Handlers\InstallationHandler;
use App\Exceptions\Handlers\MigrationHandler;
use App\Exceptions\Handlers\NoEncryptionKey;
use App\Models\Logs;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class Handler extends ExceptionHandler
{
	/**
	 * Maps class names of exceptions to their severity.
	 *
	 * By default, exceptions are logged with severity
	 * {@link Logs::SEVERITY_ERROR} by {@link Handler::report()}.
	 * This array overwrites the default severity per exception.
	 *
	 * @var array<class-string, int>
	 */
	public const EXCEPTION2SEVERITY = [
		PhotoResyncedException::class => Logs::SEVERITY_WARNING,
		PhotoSkippedException::class => Logs::SEVERITY_WARNING,
		ImportCancelledException::class => Logs::SEVERITY_NOTICE,
		ConfigurationException::class => Logs::SEVERITY_NOTICE,
		LocationDecodingFailed::class => Logs::SEVERITY_WARNING,
	];

	/**
	 * {@inheritDoc}
	 */
	protected $dontReport = [
		TokenMismatchException::class,
		SessionExpiredException::class,
	];

	/**
	 * {@inheritDoc}
	 */
	protected $internalDontReport = [];

	/** @var string the application path */
	protected string $appPath;

	public function __construct(Container $container)
	{
		parent::__construct($container);
		// Cache the application path to avoid multiple function calls
		// and potential exceptions in `report()`
		$this->appPath = app_path();
	}

	/**
	 * Maps an exception to something else.
	 *
	 * The method is called before {@link Handler::report()},
	 * {@link Handler::renderHttpException()},
	 * {@link Handler::convertExceptionToArray()}.
	 *
	 * We overwrite this method such that {@link TokenMismatchException}
	 * is not replaced by a generic HTTP exception but something more
	 * specific, which we can detect more easily.
	 * Note, that the default Laravel handler has actually **two** methods
	 * which both replace exceptions by other exceptions:
	 *
	 *  - {@link ExceptionHandler::mapException()}
	 *  - {@link ExceptionHandler::prepareException()}
	 *
	 * which are chained like one after the other like this
	 *
	 *     $e = $this->prepareException($this->mapException($e));
	 *
	 * in {@link ExceptionHandler::render()}.
	 * Indeed, it is the second method
	 * {@link ExceptionHandler::prepareException()}
	 * which replaces {@link TokenMismatchException} by a generic HTTP
	 * exception.
	 * We hook into the earlier method to prevent this.
	 *
	 * @param \Throwable $e
	 *
	 * @return \Throwable
	 */
	protected function mapException(\Throwable $e)
	{
		if ($e instanceof TokenMismatchException) {
			return new SessionExpiredException(SessionExpiredException::DEFAULT_MESSAGE, $e);
		}

		return parent::mapException($e); // TODO: Change the autogenerated stub
	}

	/**
	 * Renders the given HttpException.
	 *
	 * This method is called by the framework if
	 *  1. `config('app.debug')` is not set, i.e. the application is not in debug mode
	 *  2. the client expects an HTML response
	 *
	 * @param HttpExceptionInterface $e
	 *
	 * @return SymfonyResponse
	 *
	 * @noinspection PhpDocMissingThrowsInspection
	 * @noinspection PhpUnhandledExceptionInspection
	 */
	protected function renderHttpException(HttpExceptionInterface $e): SymfonyResponse
	{
		// If we are in debug mode, we use the internal method of the parent
		// method to render a useful response with backtrace, etc., depending
		// on the available extensions (i.e. Whoops, Symfony renderer, etc.)
		// If we are in non-debug mode, we render our own template that
		// matches Lychee's style and only contains rudimentary information.
		$defaultResponse = config('app.debug') ?
			$this->convertExceptionToResponse($e) :
			response()->view('error.error', [
				'code' => $e->getStatusCode(),
				'type' => class_basename($e),
				'message' => $e->getMessage(),
			], $e->getStatusCode(), $e->getHeaders());

		// We check, if any of our special handlers wants to do something.

		/** @var HttpExceptionHandler[] $checks */
		$checks = [
			new NoEncryptionKey(),
			new AccessDBDenied(),
			new InstallationHandler(),
			new MigrationHandler(),
		];

		foreach ($checks as $check) {
			if ($check->check($e)) {
				return $check->renderHttpException($defaultResponse, $e);
			}
		}

		return $defaultResponse;
	}

	/**
	 * Converts the given exception to an array.
	 *
	 * The result only includes details about the exception, if the
	 * application is in debug mode.
	 * Identical to
	 * {@link \Illuminate\Foundation\Exceptions\Handler::convertExceptionToAray()}
	 * but recursively adds the previous exceptions, too.
	 *
	 * @param \Throwable $e
	 *
	 * @return array
	 */
	protected function convertExceptionToArray(\Throwable $e): array
	{
		try {
			return config('app.debug') ? [
				'message' => $e->getMessage(),
				'exception' => get_class($e),
				'file' => $e->getFile(),
				'line' => $e->getLine(),
				'trace' => collect($e->getTrace())->map(function ($trace) {
					return Arr::except($trace, ['args']);
				})->all(),
				'previous_exception' => $e->getPrevious() ? $this->convertExceptionToArray($e->getPrevious()) : null,
			] : [
				'message' => $this->isHttpException($e) ? $e->getMessage() : 'Server Error',
				'exception' => class_basename($e),
			];
		} catch (\Throwable) {
			return [];
		}
	}

	/**
	 * Called by the framework if an exception occurs for logging purposes.
	 *
	 * As we have our own home-brewed logging mechanism via {@link Logs}
	 * which does not implement {@link \Psr\Log\LoggerInterface} and does
	 * not register with the service container, we override the method.
	 */
	public function report(\Throwable $e): void
	{
		$e = $this->mapException($e);

		if ($this->shouldntReport($e)) {
			return;
		}

		// We use the severity of the first exception for all subsequent
		// exceptions, because a causing exception should never be reported
		// with a higher severity than the eventual exception
		$severity = self::getLogSeverity($e);

		do {
			$cause = $this->findCause($e);

			if (count($cause) === 2) {
				Logs::log($severity, $cause[1]->getMethodBeautified(), $cause[1]->getLine(), $e->getMessage() . '; caused by');
			}

			if ($e->getPrevious() !== null) {
				Logs::log($severity, $cause[0]->getMethodBeautified(), $cause[0]->getLine(), $e->getMessage() . '; caused by');
			} else {
				Logs::log($severity, $cause[0]->getMethodBeautified(), $cause[0]->getLine(), $e->getMessage());
			}
		} while ($e = $e->getPrevious());
	}

	public static function getLogSeverity(\Throwable $e): int
	{
		return array_key_exists(get_class($e), self::EXCEPTION2SEVERITY) ?
			self::EXCEPTION2SEVERITY[get_class($e)] :
			Logs::SEVERITY_ERROR;
	}

	/**
	 * Returns up to two interesting backtrace entries which might help to
	 * pinpoint the cause of an  exception.
	 *
	 * The first backtrace entry always points the most inner function which
	 * originally has thrown the exception.
	 * The can point to a file of the Lychee source code, but may also point
	 * to a file which is part of the PHP engine or one of the libraries.
	 *
	 * The second backtrace entry is optional and - if it included - always
	 * points to the most inner method of the Lychee source code on the
	 * stack which eventually has led to the exception.
	 *
	 * Laravel's backtraces are usually hundreds of frames deep with a lot
	 * of anonymous closures in between.
	 * Printing everything only litters the log with needless entries and
	 * won't help to keep track of what really happened.
	 * The two entries above have been chosen to be the most interesting ones.
	 * The first directly points to the failing line, the second one (if not
	 * identical to the first) indicates the last line of Lychee code which
	 * has been passed before the exception occurred.
	 *
	 * The standard backtrace reported by PHP is oddly strange.
	 * The attribute pair file/line on the one hand-side and class/function
	 * on the other hand-side of a standard PHP backtrace are off-by-one.
	 * The reported file/line of an entry of the backtrace don't refer to
	 * the position *inside* the reported class/function, but where
	 * class/method has been invoked.
	 * In particular, if one wants to know the position where the
	 * exception has been thrown, then one must not look up
	 * `backtrace[0]['file']` and `backtrace[0]['line']`, resp., but
	 * use `getFile` and `getLine()` of the exception.
	 *
	 * @param \Throwable $e
	 *
	 * @return BacktraceRecord[]
	 */
	private function findCause(\Throwable $e): array
	{
		$result = [];
		$backtrace = $e->getTrace();

		// Special rule for legacy PHP errors which are caught via
		// `set_error_handler`, converted into an `ErrorException` and
		// re-injected into the "modern" exception handling procedure
		//
		// The `set_error_handler` routine is special (thank you, PHP, for
		// nothing) in two ways: (a) the `file` parameter is not filled
		// (WTF?), and (b) the top entry of the backtrace points to
		// `set_error_handler` which is not really part of the frame stack
		// and does not provide any helpful information.
		//
		// For all who don't know the background: PHP provides two different
		// approaches to indicate and handle error conditions which both
		// interrupt the normal program flow:
		//
		//  a) the engine error reporting system (legacy approach)
		//  b) exceptions (modern approach)
		//
		// The legacy approach is very similar to POSIX signal handling in the
		// sense that one can register a static, global error handler and the
		// PHP engine calls this handler whenever some error has occurred
		// anywhere in the program.
		// This error handler is not part of the normal program stack, but
		// "lives" outside the normal program stack.
		// When the error handler returns, the normal program flow and call
		// stack is resumed.
		//
		// The modern approach uses exceptions which bubble up the call stack
		// until they are caught and handled.
		//
		// In order to unify the error handling, the default `error_handler`
		// nowadays wraps the reported error into a `\ErrorException` which
		// then is thrown as if it was thrown by the method which caused the
		// error in the first place.
		// Unfortunately, this messes with the backtrace.
		//
		// Hopefully, the whole legacy PHP error reporting system will be
		// nuked some day.
		// PHP 8 made a great step into that direction
		// (e.g., see https://wiki.php.net/rfc/consistent_type_errors,
		// https://wiki.php.net/rfc/engine_warnings,
		// https://wiki.php.net/rfc/lsp_errors).
		// I really like the sentence about the dark ages of PHP ;-).
		//
		// And hopefully, this is the only special rule we need and nobody
		// never ever misuses `\ErrorException` for "normal" exceptions.
		$offset = $e instanceof \ErrorException ? 1 : 0;

		$file = $e->getFile();
		$line = $e->getLine();
		$class = $backtrace[$offset]['class'] ?? '';
		$function = $backtrace[$offset]['function'] ?? '';

		// Always add the most inner frame
		$result[] = new BacktraceRecord(
			$file, $line, $class, $function
		);

		// If this frame is part of our own code, we are done.
		// We are also done, if there are no more frame on the backtrace
		if (str_contains($file, $this->appPath) || count($backtrace) <= $offset + 1) {
			return $result;
		}

		// Try to find the most inner method of our own code

		// Normally, every backtrace entry must have a `file` and `line`
		// attribute.
		// But in view of the problems with legacy error handling, this
		// must not be taken for granted.
		// It seems that for certain low level methods which are part of
		// the PHP engine (like `fopen`) this cannot be taken for granted.
		// As this method must not fail, we are better safe than sorry.
		$file = $backtrace[$offset]['file'] ?? '';
		$line = $backtrace[$offset]['line'] ?? 0;

		for ($idx = $offset + 1; $idx < count($backtrace); $idx++) {
			$class = $backtrace[$idx]['class'] ?? '';
			$function = $backtrace[$idx]['function'] ?? '';
			// If this frame is part of our own code, we are done.
			if (str_contains($file, $this->appPath)) {
				break;
			}
			$file = $backtrace[$idx]['file'] ?? '';
			$line = $backtrace[$idx]['line'] ?? 0;
		}

		$result[] = new BacktraceRecord(
			$file, $line, $class, $function
		);

		return $result;
	}
}
