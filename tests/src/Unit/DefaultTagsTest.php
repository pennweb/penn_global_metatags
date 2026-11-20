<?php

namespace Drupal\Tests\penn_global_metatags\Unit;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\penn_global_metatags\DefaultTags;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests merging the module's tags into metatag defaults.
 */
#[Group('penn_global_metatags')]
class DefaultTagsTest extends UnitTestCase {

  /**
   * Current tags per defaults ID, updated when an entity is saved.
   *
   * @var array<string, array<string, string>|null>
   */
  protected array $tags = [];

  /**
   * Tests that missing tags are added and a site's own values are kept.
   */
  public function testApplyAddsOnlyMissingTags(): void {
    $this->tags = [
      'front' => ['schema_organization_type' => 'CollegeOrUniversity'],
      'node' => ['title' => '[node:title] | [site:name]', 'schema_web_page_name' => ''],
      'taxonomy_term' => DefaultTags::DEFAULTS['taxonomy_term'],
    ];

    $added = $this->createService()->apply();

    $this->assertSame('CollegeOrUniversity', $this->tags['front']['schema_organization_type']);
    $this->assertNotContains('schema_organization_type', $added['front']);
    $this->assertSame('[node:title] | [site:name]', $this->tags['node']['title']);
    // An empty value counts as unset.
    $this->assertSame('[node:title]', $this->tags['node']['schema_web_page_name']);
    // Every default is now set; key order follows the site's existing tags.
    $expected = DefaultTags::DEFAULTS['node'];
    $actual = array_intersect_key($this->tags['node'], $expected);
    ksort($expected);
    ksort($actual);
    $this->assertSame($expected, $actual);
    // Fully set already: nothing reported, nothing saved.
    $this->assertArrayNotHasKey('taxonomy_term', $added);
  }

  /**
   * Tests that a defaults entity the site lacks is skipped, not created.
   */
  public function testApplySkipsMissingDefaults(): void {
    $this->tags = ['front' => NULL, 'node' => [], 'taxonomy_term' => NULL];

    $added = $this->createService()->apply();

    $this->assertSame(['node'], array_keys($added));
    $this->assertNull($this->tags['front']);
  }

  /**
   * Tests that uninstall removes own tags but keeps customized standard tags.
   */
  public function testRemoveKeepsCustomizedValues(): void {
    $this->tags = [
      'front' => DefaultTags::DEFAULTS['front'] + ['title' => 'Penn'],
      'node' => ['schema_web_page_type' => 'AboutPage', 'schema_web_page_name' => 'Custom name'] + DefaultTags::DEFAULTS['node'],
      'taxonomy_term' => NULL,
    ];

    $this->createService()->remove();

    $this->assertSame(['title' => 'Penn'], $this->tags['front']);
    // A standard tag the site changed stays; this module's own plugin tag goes
    // whatever its value, since the plugin no longer exists.
    $this->assertSame(['schema_web_page_type' => 'AboutPage'], $this->tags['node']);
  }

  /**
   * Creates the service over in-memory metatag defaults.
   */
  protected function createService(): DefaultTags {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('load')->willReturnCallback(function (string $id): ?ConfigEntityInterface {
      if (!isset($this->tags[$id])) {
        return NULL;
      }
      $pending = $this->tags[$id];
      $entity = $this->createMock(ConfigEntityInterface::class);
      $entity->method('get')->willReturnCallback(fn(string $key) => $key === 'tags' ? $pending : NULL);
      $entity->method('set')->willReturnCallback(function (string $key, $value) use (&$pending, $entity) {
        $pending = $value;
        return $entity;
      });
      $entity->method('save')->willReturnCallback(function () use (&$pending, $id): int {
        $this->tags[$id] = $pending;
        return 2;
      });
      return $entity;
    });

    $manager = $this->createMock(EntityTypeManagerInterface::class);
    $manager->method('getStorage')->with('metatag_defaults')->willReturn($storage);

    return new DefaultTags($manager);
  }

}
