<?php

namespace Drupal\Tests\penn_global_metatags\Unit;

use Drupal\penn_global_metatags\Plugin\metatag\Tag\SchemaWebPageBreadcrumbTrail;
use Drupal\penn_global_metatags\Plugin\metatag\Tag\SchemaWebPageIsPartOf;
use Drupal\penn_global_metatags\Plugin\metatag\Tag\SchemaWebPageMainEntity;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the output of the module's schema.org WebPage tags.
 */
#[Group('penn_global_metatags')]
class SchemaWebPageTagsTest extends UnitTestCase {

  /**
   * Tags that should reference another graph node by @id.
   */
  public static function idReferenceTags(): array {
    return [
      'isPartOf' => [SchemaWebPageIsPartOf::class],
      'mainEntity' => [SchemaWebPageMainEntity::class],
    ];
  }

  /**
   * Tests that @id-reference tags wrap their URL as {"@id": ...}.
   */
  #[DataProvider('idReferenceTags')]
  public function testIdReferenceTagsEmitIdObjects(string $class): void {
    $plugin = (new \ReflectionClass($class))->newInstanceWithoutConstructor();

    $this->assertSame(
      ['@id' => 'https://penntoday.upenn.edu/#website'],
      $plugin->outputValue('https://penntoday.upenn.edu/#website'),
    );
  }

  /**
   * Tests that breadcrumb positions are emitted as integers.
   */
  public function testBreadcrumbPositionsAreIntegers(): void {
    $plugin = (new \ReflectionClass(SchemaWebPageBreadcrumbTrail::class))
      ->newInstanceWithoutConstructor();

    $output = $plugin->outputValue([
      '@type' => 'BreadcrumbList',
      'itemListElement' => [
        ['@type' => 'ListItem', 'position' => '1', 'name' => 'Home'],
        ['@type' => 'ListItem', 'position' => '2', 'name' => 'Arts & Humanities'],
      ],
    ]);

    $this->assertSame([1, 2], array_column($output['itemListElement'], 'position'));
  }

}
