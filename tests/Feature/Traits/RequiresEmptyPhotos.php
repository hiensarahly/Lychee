<?php

/**
 * We don't care for unhandled exceptions in tests.
 * It is the nature of a test to throw an exception.
 * Without this suppression we had 100+ Linter warning in this file which
 * don't help anything.
 *
 * @noinspection PhpDocMissingThrowsInspection
 * @noinspection PhpUnhandledExceptionInspection
 */

namespace Tests\Feature\Traits;

use Illuminate\Support\Facades\DB;

trait RequiresEmptyPhotos
{
	protected function setUpRequiresEmptyPhotos(): void
	{
		// Assert that photo table is empty
		static::assertDatabaseCount('sym_links', 0);
		static::assertDatabaseCount('size_variants', 0);
		static::assertDatabaseCount('photos', 0);
	}

	protected function tearDownRequiresEmptyPhotos(): void
	{
		// Clean up remaining stuff from tests
		DB::table('sym_links')->delete();
		DB::table('size_variants')->delete();
		DB::table('photos')->delete();
		self::cleanPublicFolders();
	}

	/**
	 * Cleans the "public" folders 'uploads' and 'sym'.
	 *
	 * Removes all files from the directories except for sub-directories and
	 * 'index.html'.
	 *
	 * @return void
	 */
	protected static function cleanPublicFolders(): void
	{
		self::cleanupHelper(base_path('public/uploads/'));
		self::cleanupHelper(base_path('public/sym/'));
	}

	/**
	 * Cleans the designated directory recursively.
	 *
	 * Removes all files from the directories except for sub-directories and
	 * 'index.html'.
	 *
	 * @param string $dirPath the path of the directory
	 *
	 * @return void
	 */
	private static function cleanupHelper(string $dirPath): void
	{
		if (!is_dir($dirPath)) {
			return;
		}
		\Safe\chmod($dirPath, 0775);
		$dirEntries = scandir($dirPath);
		foreach ($dirEntries as $dirEntry) {
			if (in_array($dirEntry, ['.', '..', 'index.html', '.gitignore'])) {
				continue;
			}

			$dirEntryPath = $dirPath . DIRECTORY_SEPARATOR . $dirEntry;
			if (is_dir($dirEntryPath) && !is_link($dirEntryPath)) {
				self::cleanupHelper($dirEntryPath);
			}
			if (is_file($dirEntryPath) || is_link($dirEntryPath)) {
				unlink($dirEntryPath);
			}
		}
	}

	abstract protected function assertDatabaseCount($table, int $count, $connection = null);
}