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

namespace Tests\Feature\Lib;

use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AlbumsUnitTest
{
	private TestCase $testCase;

	public function __construct(TestCase $testCase)
	{
		$this->testCase = $testCase;
	}

	/**
	 * Add an album.
	 *
	 * @param string|null $parent_id
	 * @param string      $title
	 * @param int         $expectedStatusCode
	 * @param string|null $assertSee
	 *
	 * @return TestResponse
	 */
	public function add(
		?string $parent_id,
		string $title,
		int $expectedStatusCode = 201,
		?string $assertSee = null
	): TestResponse {
		$response = $this->testCase->postJson('/api/album', [
			'title' => $title,
			'parent_id' => $parent_id,
		]);
		$response->assertStatus($expectedStatusCode);
		if ($assertSee) {
			$response->assertSee($assertSee, false);
		}

		return $response;
	}

	/**
	 * Add an album.
	 *
	 * @param string      $title
	 * @param string[]    $tags
	 * @param int         $expectedStatusCode
	 * @param string|null $assertSee
	 *
	 * @return TestResponse
	 */
	public function addByTags(
		string $title,
		array $tags,
		int $expectedStatusCode = 201,
		?string $assertSee = null
	): TestResponse {
		$response = $this->testCase->postJson('/api/album/tag', [
			'title' => $title,
			'tags' => $tags,
		]);
		$response->assertStatus($expectedStatusCode);
		if ($assertSee) {
			$response->assertSee($assertSee, false);
		}

		return $response;
	}

	/**
	 * Move albums.
	 *
	 * @param string[]    $ids
	 * @param string|null $to
	 * @param int         $expectedStatusCode
	 * @param string|null $assertSee
	 */
	public function move(
		array $ids,
		?string $to,
		int $expectedStatusCode = 204,
		?string $assertSee = null
	): void {
		$response = $this->testCase->postJson('/api/Album::move', [
			'albumID' => $to,
			'albumIDs' => $ids,
		]);
		$response->assertStatus($expectedStatusCode);
		if ($assertSee) {
			$response->assertSee($assertSee, false);
		}
	}

	/**
	 * Get album by ID.
	 *
	 * @param string      $id
	 * @param int         $expectedStatusCode
	 * @param string|null $assertSee
	 *
	 * @return TestResponse
	 */
	public function get(
		string $id,
		int $expectedStatusCode = 200,
		?string $assertSee = null
	): TestResponse {
		$response = $this->testCase->getJson(
			'/api/album/' . $id
		);
		$response->assertStatus($expectedStatusCode);
		if ($assertSee) {
			$response->assertSee($assertSee, false);
		}

		return $response;
	}

	/**
	 * @param string      $id
	 * @param string      $password
	 * @param int         $expectedStatusCode
	 * @param string|null $assertSee
	 */
	public function unlock(
		string $id,
		string $password = '',
		int $expectedStatusCode = 200,
		?string $assertSee = null
	): void {
		$response = $this->testCase->postJson(
			'/api/album/' . $id . '/unlock',
			['password' => $password]
		);
		$response->assertStatus($expectedStatusCode);
		if ($assertSee) {
			$response->assertSee($assertSee, false);
		}
	}

	/**
	 * Check if we see `id` in the list of all visible albums.
	 *
	 * Result varies depending on login state.
	 *
	 * @param string $id
	 */
	public function see_in_albums(string $id): void
	{
		$response = $this->testCase->getJson('/api/albums');
		$response->assertOk();
		$response->assertSee($id, false);
	}

	/**
	 * Check if we don't see id in the list of all visible albums.
	 *
	 * Result varies depending on login state!
	 *
	 * @param string $id
	 */
	public function dont_see_in_albums(string $id): void
	{
		$response = $this->testCase->getJson('/api/albums');
		$response->assertOk();
		$response->assertDontSee($id, false);
	}

	/**
	 * Change title.
	 *
	 * @param string      $id
	 * @param string      $title
	 * @param int         $expectedStatusCode
	 * @param string|null $assertSee
	 */
	public function set_title(
		string $id,
		string $title,
		int $expectedStatusCode = 204,
		?string $assertSee = null
	): void {
		$response = $this->testCase->postJson(
			'/api/Album::setTitle',
			['albumIDs' => [$id], 'title' => $title]
		);
		$response->assertStatus($expectedStatusCode);
		if ($assertSee) {
			$response->assertSee($assertSee, false);
		}
	}

	/**
	 * Change description.
	 *
	 * @param string      $id
	 * @param string      $description
	 * @param int         $expectedStatusCode
	 * @param string|null $assertSee
	 */
	public function set_description(
		string $id,
		string $description,
		int $expectedStatusCode = 204,
		?string $assertSee = null
	): void {
		$response = $this->testCase->postJson(
			'/api/album/' . $id . '/description',
			['description' => $description]
		);
		$response->assertStatus($expectedStatusCode);
		if ($assertSee) {
			$response->assertSee($assertSee, false);
		}
	}

	/**
	 * Set the licence.
	 *
	 * @param string      $id
	 * @param string      $license
	 * @param int         $expectedStatusCode
	 * @param string|null $assertSee
	 */
	public function set_license(
		string $id,
		string $license,
		int $expectedStatusCode = 204,
		?string $assertSee = null
	): void {
		$response = $this->testCase->postJson('/api/album/' . $id . '/license', [
			'license' => $license,
		]);
		$response->assertStatus($expectedStatusCode);
		if ($assertSee) {
			$response->assertSee($assertSee, false);
		}
	}

	/**
	 * Set sorting.
	 *
	 * @param string      $id
	 * @param string      $sortingCol
	 * @param string      $sortingOrder
	 * @param int         $expectedStatusCode
	 * @param string|null $assertSee
	 */
	public function set_sorting(
		string $id,
		string $sortingCol,
		string $sortingOrder,
		int $expectedStatusCode = 204,
		?string $assertSee = null
	): void {
		$response = $this->testCase->postJson('/api/album/' . $id . '/sorting', [
			'sorting_column' => $sortingCol,
			'sorting_order' => $sortingOrder,
		]);
		$response->assertStatus($expectedStatusCode);
		if ($assertSee) {
			$response->assertSee($assertSee, false);
		}
	}

	/**
	 * @param string      $id
	 * @param bool        $full_photo
	 * @param bool        $public
	 * @param bool        $requiresLink
	 * @param bool        $nsfw
	 * @param bool        $downloadable
	 * @param bool        $share_button_visible
	 * @param int         $expectedStatusCode
	 * @param string|null $assertSee
	 */
	public function set_protection_policy(
		string $id,
		bool $full_photo = true,
		bool $public = true,
		bool $requiresLink = false,
		bool $nsfw = false,
		bool $downloadable = true,
		bool $share_button_visible = true,
		int $expectedStatusCode = 204,
		?string $assertSee = null
	): void {
		$response = $this->testCase->postJson('/api/album/' . $id . '/protection', [
			'grants_full_photo' => $full_photo,
			'is_public' => $public,
			'requires_link' => $requiresLink,
			'is_nsfw' => $nsfw,
			'is_downloadable' => $downloadable,
			'is_share_button_visible' => $share_button_visible,
		]);
		$response->assertStatus($expectedStatusCode);
		if ($assertSee) {
			$response->assertSee($assertSee, false);
		}
	}

	/**
	 * @param string      $id
	 * @param string[]    $tags
	 * @param int         $expectedStatusCode
	 * @param string|null $assertSee
	 */
	public function set_tags(
		string $id,
		array $tags,
		int $expectedStatusCode = 204,
		?string $assertSee = null
	): void {
		$response = $this->testCase->postJson('/api/album/' . $id . '/tags', [
			'show_tags' => $tags,
		]);
		$response->assertStatus($expectedStatusCode);
		if ($assertSee) {
			$response->assertSee($assertSee, false);
		}
	}

	/**
	 * We only test for a code 200.
	 *
	 * @param string $id
	 */
	public function download(string $id): void
	{
		$response = $this->testCase->getWithParameters(
			'/api/Album::getArchive', [
				'albumIDs' => $id,
			], [
				'Accept' => '*/*',
			]
		);
		$response->assertOk();
	}

	/**
	 * Delete.
	 *
	 * @param string[]    $ids
	 * @param int         $expectedStatusCode
	 * @param string|null $assertSee
	 */
	public function delete(
		array $ids,
		int $expectedStatusCode = 204,
		?string $assertSee = null
	): void {
		$response = $this->testCase->deleteJson('/api/Album::delete', ['albumIDs' => $ids]);
		$response->assertStatus($expectedStatusCode);
		if ($assertSee) {
			$response->assertSee($assertSee, false);
		}
	}

	/**
	 * Test position data (Albums).
	 *
	 * @param int         $expectedStatusCode
	 * @param string|null $assertSee
	 *
	 * @return TestResponse
	 */
	public function AlbumsGetPositionDataFull(
		int $expectedStatusCode = 200,
		?string $assertSee = null
	): TestResponse {
		$response = $this->testCase->getJson('/api/albums/position');
		$response->assertStatus($expectedStatusCode);
		if ($assertSee) {
			$response->assertSee($assertSee, false);
		}

		return $response;
	}

	/**
	 * Test position data (Album).
	 *
	 * @param string      $id
	 * @param int         $expectedStatusCode
	 * @param string|null $assertSee
	 *
	 * @return TestResponse
	 */
	public function AlbumGetPositionDataFull(
		string $id,
		int $expectedStatusCode = 200,
		?string $assertSee = null
	): TestResponse {
		$response = $this->testCase->getJson('/api/album/' . $id . 'position?includeSubAlbums=0');
		$response->assertStatus($expectedStatusCode);
		if ($assertSee) {
			$response->assertSee($assertSee, false);
		}

		return $response;
	}
}
