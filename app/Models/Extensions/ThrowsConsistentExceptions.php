<?php

namespace App\Models\Extensions;

use App\Exceptions\ModelDBException;
use Illuminate\Database\Eloquent\JsonEncodingException;

/**
 * Fixed Eloquent model for all Lychee models.
 *
 * This trait wraps some Eloquent model methods to make error handling
 * more consistent.
 * Some Eloquent methods report error conditions by using a mix of returning
 * `false` or throwing an exception.
 * This makes proper error handling a tedious task, because we always have
 * to check for two possible conditions.
 * This model unifies error reporting and also throws an exception when
 * the original parent method would return `false`.
 */
trait ThrowsConsistentExceptions
{
	abstract protected function friendlyModelName(): string;

	abstract public function toArray(): array;

	/**
	 * @param array $options
	 *
	 * @return bool always return true
	 *
	 * @throws ModelDBException thrown on failure
	 *
	 * @noinspection PhpMultipleClassDeclarationsInspection
	 */
	public function save(array $options = []): bool
	{
		$parentException = null;
		try {
			// Note, `Model::save` may also return `null` which also indicates a success
			if (parent::save($options) === false) {
				$parentException = new \RuntimeException('Eloquent\Model::save() returned false');
			}
		} catch (\Throwable $e) {
			$parentException = $e;
		}
		if ($parentException) {
			throw ModelDBException::create($this->friendlyModelName(), $this->wasRecentlyCreated ? 'create' : 'update', $parentException);
		}

		return true;
	}

	/**
	 * @return bool always return true
	 *
	 * @throws ModelDBException thrown on failure
	 *
	 * @noinspection PhpMultipleClassDeclarationsInspection
	 */
	public function delete(): bool
	{
		$parentException = null;
		try {
			// Sic! Don't use `!$parentDelete` in condition, because we also
			// need to proceed if `$parentDelete === null` .
			// If Eloquent returns `null` (instead of `true`), this also
			// indicates a success, and we must go on.
			// Eloquent, I love you .... not.
			$result = parent::delete();
			if ($result === false) {
				$parentException = new \RuntimeException('Eloquent\Model::delete() returned false');
			}
		} catch (\Throwable $e) {
			$parentException = $e;
		}
		if ($parentException) {
			throw ModelDBException::create($this->friendlyModelName(), 'delete', $parentException);
		}

		return true;
	}

	/**
	 * Serializes this object into an array.
	 *
	 * @return array The serialized properties of this object
	 *
	 * @throws \JsonException
	 *
	 * @see ThrowsConsistentExceptions::toArray()
	 */
	public function jsonSerialize(): array
	{
		try {
			return $this->toArray();
		} catch (\Exception $e) {
			throw new \JsonException(get_class($this) . '::toArray() failed', 0, $e);
		}
	}

	/**
	 * Convert the model instance to JSON.
	 *
	 * The error message is inspired by {@link JsonEncodingException::forModel()}.
	 *
	 * @param int $options
	 *
	 * @return string
	 *
	 * @throws JsonEncodingException
	 */
	public function toJson($options = 0): string
	{
		try {
			return json_encode($this->jsonSerialize(), $options | JSON_THROW_ON_ERROR);
		} catch (\JsonException $e) {
			throw new JsonEncodingException('Error encoding model [' . get_class($this) . '] with ID [' . $this->getKey() . '] to JSON', 0, $e);
		}
	}
}
