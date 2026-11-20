<?php

namespace Drupal\Tests\penn_global_metatags\Unit;

use Drupal\penn_global_metatags\VimeoMetadata;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Vimeo ID extraction and oEmbed normalization.
 */
#[Group('penn_global_metatags')]
class VimeoMetadataTest extends UnitTestCase {

  /**
   * The values editors paste, and the ID each should yield.
   */
  public static function vimeoValues(): array {
    return [
      'bare id' => ['123456789', '123456789'],
      'watch url' => ['https://vimeo.com/123456789', '123456789'],
      'player url' => ['https://player.vimeo.com/video/123456789?h=abc', '123456789'],
      'channel url' => ['https://vimeo.com/channels/staffpicks/123456789', '123456789'],
      'not vimeo' => ['https://www.youtube.com/watch?v=abc', NULL],
      'empty' => ['', NULL],
    ];
  }

  /**
   * Tests ID extraction across pasted formats.
   */
  #[DataProvider('vimeoValues')]
  public function testExtractId(string $value, ?string $expected): void {
    $this->assertSame($expected, $this->createService()->extractId($value));
  }

  /**
   * Tests that only usable oEmbed values are kept.
   */
  public function testNormalizeKeepsUsableFields(): void {
    $data = $this->createService()->normalize([
      'title' => 'P-1234_upload_slug',
      'duration' => 95,
      'upload_date' => '2026-05-01 10:00:00',
      'thumbnail_url' => 'https://i.vimeocdn.com/video/1_640.jpg',
      'description' => '<p>Commencement highlights.</p>',
    ]);

    $this->assertSame(95, $data['duration']);
    $this->assertSame('https://i.vimeocdn.com/video/1_640.jpg', $data['thumbnail_url']);
    $this->assertSame('Commencement highlights.', $data['description']);
    $this->assertStringStartsWith('2026-05-01T', $data['upload_date']);
    $this->assertArrayNotHasKey('title', $data);
  }

  /**
   * Tests that a restricted response (no duration, no thumbnail) yields nothing.
   */
  public function testNormalizeDropsRestrictedPayload(): void {
    $this->assertSame([], $this->createService()->normalize(['html' => '<iframe></iframe>', 'duration' => 0]));
  }

  /**
   * Creates the service without its collaborators; these methods need none.
   */
  protected function createService(): VimeoMetadata {
    return (new \ReflectionClass(VimeoMetadata::class))->newInstanceWithoutConstructor();
  }

}
