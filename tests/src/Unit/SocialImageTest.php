<?php

namespace Drupal\Tests\penn_global_metatags\Unit;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;
use Drupal\penn_global_metatags\SocialImage;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests picking the social sharing image.
 */
#[Group('penn_global_metatags')]
class SocialImageTest extends UnitTestCase {

  /**
   * The configured default image.
   */
  protected string $defaultImage = '';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Cache::mergeContexts() asserts context tokens through the container.
    $contexts = $this->createMock(CacheContextsManager::class);
    $contexts->method('assertValidTokens')->willReturn(TRUE);
    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $contexts);
    \Drupal::setContainer($container);
  }

  /**
   * The ways a site can enter its default image, and the URL each yields.
   */
  public static function imageValues(): array {
    return [
      'absolute url' => ['https://cdn.example.edu/share.jpg', 'https://cdn.example.edu/share.jpg'],
      'site path' => ['/themes/custom/mytheme/share.jpg', 'https://www.example.edu/themes/custom/mytheme/share.jpg'],
      'stream uri' => ['public://share.jpg', 'https://www.example.edu/sites/default/files/share.jpg'],
      'empty' => ['', ''],
    ];
  }

  /**
   * Tests resolving the configured default image to an absolute URL.
   */
  #[DataProvider('imageValues')]
  public function testSiteImageResolvesToAbsoluteUrl(string $value, string $expected): void {
    $this->defaultImage = $value;

    $image = $this->createService()->getSiteImage(new BubbleableMetadata());

    $this->assertSame($expected, $image['url']);
    $this->assertSame($expected === '' ? '' : 'Campus at dusk', $image['alt']);
  }

  /**
   * Tests that a node's own image field wins over the site default.
   */
  public function testNodeUsesItsImageField(): void {
    $this->defaultImage = '/default.jpg';
    $node = $this->createNode([
      'title' => ['string', TRUE, NULL],
      'field_tags' => ['entity_reference', FALSE, $this->createField(['entity' => NULL]), 'taxonomy_term'],
      'field_image' => ['image', FALSE, $this->createField(['entity' => $this->createFile('public://story.jpg'), 'alt' => 'A lab'])],
    ]);

    $image = $this->createService()->getNodeImage($node, new BubbleableMetadata());

    $this->assertSame(['url' => 'https://www.example.edu/sites/default/files/story.jpg', 'alt' => 'A lab'], $image);
  }

  /**
   * Tests that a media reference uses the media item's thumbnail.
   */
  public function testNodeUsesMediaThumbnail(): void {
    $media = $this->createMock(FieldableEntityInterface::class);
    $this->configureCacheability($media);
    $thumbnail = $this->createField(['entity' => $this->createFile('public://media/hero.jpg'), 'alt' => 'Hero']);
    $media->method('hasField')->willReturn(TRUE);
    $media->method('get')->willReturn($thumbnail);

    $node = $this->createNode([
      'field_hero' => ['entity_reference', FALSE, $this->createField(['entity' => $media]), 'media'],
    ]);

    $image = $this->createService()->getNodeImage($node, new BubbleableMetadata());

    $this->assertSame('https://www.example.edu/sites/default/files/media/hero.jpg', $image['url']);
    $this->assertSame('Hero', $image['alt']);
  }

  /**
   * Tests that a node without images falls back to the site default.
   */
  public function testNodeFallsBackToSiteImage(): void {
    $this->defaultImage = '/default.jpg';

    $image = $this->createService()->getNodeImage($this->createNode([]), new BubbleableMetadata());

    $this->assertSame('https://www.example.edu/default.jpg', $image['url']);
  }

  /**
   * Creates the service with mocked collaborators.
   */
  protected function createService(): SocialImage {
    $settings = $this->createMock(ImmutableConfig::class);
    $this->configureCacheability($settings);
    $settings->method('get')->willReturnCallback(fn(string $key): string => match ($key) {
      'default_image' => $this->defaultImage,
      'default_image_alt' => 'Campus at dusk',
      default => '',
    });
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->willReturn($settings);

    $file_urls = $this->createMock(FileUrlGeneratorInterface::class);
    $file_urls->method('generateAbsoluteString')
      ->willReturnCallback(static fn(string $uri): string => 'https://www.example.edu/sites/default/files/' . substr($uri, strlen('public://')));

    $requests = new RequestStack();
    $requests->push(Request::create('https://www.example.edu/about'));

    return new SocialImage($config_factory, $file_urls, $requests);
  }

  /**
   * Creates a node with the given fields.
   *
   * @param array<string, array> $fields
   *   Keyed by field name: [type, is base field, field list, target type].
   */
  protected function createNode(array $fields): NodeInterface {
    $definitions = [];
    $lists = [];
    foreach ($fields as $name => $spec) {
      [$type, $is_base, $list] = $spec;
      $target_type = $spec[3] ?? NULL;

      $storage = $this->createMock(FieldStorageDefinitionInterface::class);
      $storage->method('isBaseField')->willReturn($is_base);
      $definition = $this->createMock(FieldDefinitionInterface::class);
      $definition->method('getFieldStorageDefinition')->willReturn($storage);
      $definition->method('getType')->willReturn($type);
      $definition->method('getSetting')->willReturnCallback(static fn(string $key) => $key === 'target_type' ? $target_type : NULL);

      $definitions[$name] = $definition;
      $lists[$name] = $list ?? $this->createField([]);
    }

    $node = $this->createMock(NodeInterface::class);
    $this->configureCacheability($node);
    $node->method('getFieldDefinitions')->willReturn($definitions);
    $node->method('get')->willReturnCallback(static fn(string $name): FieldItemListInterface => $lists[$name]);

    return $node;
  }

  /**
   * Creates a populated field mock with magic property access.
   */
  protected function createField(array $properties): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('isEmpty')->willReturn(empty($properties['entity']));
    $field->method('__get')->willReturnCallback(static fn(string $name): mixed => $properties[$name] ?? NULL);
    $field->method('__isset')->willReturnCallback(static fn(string $name): bool => isset($properties[$name]));

    return $field;
  }

  /**
   * Creates a file mock with the given URI.
   */
  protected function createFile(string $uri): FileInterface {
    $file = $this->createMock(FileInterface::class);
    $this->configureCacheability($file);
    $file->method('getFileUri')->willReturn($uri);

    return $file;
  }

  /**
   * Gives a mock empty, permanent cacheability.
   */
  protected function configureCacheability(CacheableDependencyInterface $object): void {
    $object->method('getCacheContexts')->willReturn([]);
    $object->method('getCacheTags')->willReturn([]);
    $object->method('getCacheMaxAge')->willReturn(Cache::PERMANENT);
  }

}
