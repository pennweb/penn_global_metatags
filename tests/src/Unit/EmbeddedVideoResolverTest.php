<?php

namespace Drupal\Tests\penn_global_metatags\Unit;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\penn_global_metatags\EmbeddedVideoResolver;
use Drupal\penn_global_metatags\VimeoMetadata;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests VideoObject values for embedded video components.
 */
#[Group('penn_global_metatags')]
class EmbeddedVideoResolverTest extends UnitTestCase {

  private const UUID = '12345678-abcd-1234-abcd-1234567890ab';

  /**
   * Embedded components keyed by UUID, served by the storage mock.
   *
   * @var \Drupal\Core\Entity\FieldableEntityInterface[]
   */
  protected array $components = [];

  /**
   * Cached Vimeo metadata keyed by video ID.
   */
  protected array $vimeoCache = [];

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
   * Tests that a Vimeo component yields embedUrl plus Vimeo's metadata.
   */
  public function testVimeoComponent(): void {
    $this->components[self::UUID] = $this->createComponent('video_vimeo', [
      'field_video_source_url' => $this->createField(['value' => 'https://vimeo.com/987654']),
    ]);
    $this->vimeoCache['987654'] = [
      'duration' => 95,
      'upload_date' => '2026-05-01T10:00:00-04:00',
      'thumbnail_url' => 'https://i.vimeocdn.com/video/1_640.jpg',
    ];

    $video = $this->createResolver()->getVideo($this->createNode(), new BubbleableMetadata());

    $this->assertSame('VideoObject', $video['type']);
    $this->assertSame('https://www.upenn.edu/about#video', $video['id']);
    $this->assertSame('About Penn', $video['name']);
    $this->assertSame('https://player.vimeo.com/video/987654', $video['embed-url']);
    $this->assertSame(95, $video['duration']);
    $this->assertSame('2026-05-01T10:00:00-04:00', $video['upload-date']);
    $this->assertSame('https://i.vimeocdn.com/video/1_640.jpg', $video['thumbnail-url']);
    $this->assertSame('An introduction to the University.', $video['description']);
    $this->assertArrayNotHasKey('content-url', $video);
  }

  /**
   * Tests that a file component yields contentUrl and its own created date.
   */
  public function testFileComponent(): void {
    $this->components[self::UUID] = $this->createComponent('video_modal', [
      'field_video_source' => $this->createField(['uri' => 'https://www.upenn.edu/sites/default/files/tour.mp4']),
      'created' => $this->createField(['value' => 1767225600]),
    ]);

    $video = $this->createResolver()->getVideo($this->createNode(), new BubbleableMetadata());

    $this->assertSame('https://www.upenn.edu/sites/default/files/tour.mp4', $video['content-url']);
    $this->assertSame(date('c', 1767225600), $video['upload-date']);
    $this->assertArrayNotHasKey('embed-url', $video);
    $this->assertArrayNotHasKey('duration', $video);
  }

  /**
   * Tests that unlisted bundles (e.g. looping background clips) are ignored.
   */
  public function testUnlistedBundleIsIgnored(): void {
    $this->components[self::UUID] = $this->createComponent('video_inline', [
      'field_video_source' => $this->createField(['uri' => 'https://www.upenn.edu/sites/default/files/loop.mp4']),
    ]);

    $resolver = $this->createResolver();
    $this->assertNull($resolver->getVideo($this->createNode(), new BubbleableMetadata()));
    $this->assertSame([], $resolver->resolveTokens(['schema-video-type'], $this->createNode(), new BubbleableMetadata()));
  }

  /**
   * Tests that token names map onto the resolved values.
   */
  public function testResolveTokens(): void {
    $this->components[self::UUID] = $this->createComponent('video_modal', [
      'field_video_source' => $this->createField(['uri' => 'https://www.upenn.edu/sites/default/files/tour.mp4']),
    ]);

    $values = $this->createResolver()->resolveTokens(
      ['schema-video-type', 'schema-video-content-url', 'schema-video-embed-url'],
      $this->createNode(),
      new BubbleableMetadata(),
    );

    $this->assertSame([
      'schema-video-type' => 'VideoObject',
      'schema-video-content-url' => 'https://www.upenn.edu/sites/default/files/tour.mp4',
    ], $values);
  }

  /**
   * Tests embed parsing, including attribute order and non-entity tags.
   */
  public function testGetEmbeds(): void {
    $html = '<p>x</p><drupal-entity data-entity-uuid="aaaa-1" data-entity-type="penn_entity"></drupal-entity>'
      . '<drupal-entity data-embed-button="penn_entity" data-entity-type="media" data-entity-uuid="bbbb-2"></drupal-entity>';

    $this->assertSame(
      [['penn_entity', 'aaaa-1'], ['media', 'bbbb-2']],
      $this->createResolver()->getEmbeds($html),
    );
  }

  /**
   * Creates the resolver with mocked collaborators.
   */
  protected function createResolver(): EmbeddedVideoResolver {
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')
      ->willReturnCallback(fn(array $values): array => isset($this->components[$values['uuid']]) ? [$this->components[$values['uuid']]] : []);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->willReturn($storage);

    $settings = $this->createMock(ImmutableConfig::class);
    $this->configureCacheability($settings);
    $settings->method('get')->willReturnCallback(static fn(string $key): mixed => match ($key) {
      'scan_fields' => ['body'],
      'video_sources' => [
        ['entity_type' => 'penn_entity', 'bundle' => 'video_modal', 'url_field' => 'field_video_source', 'url_type' => 'file', 'poster_field' => 'field_image'],
        ['entity_type' => 'penn_entity', 'bundle' => 'video_vimeo', 'url_field' => 'field_video_source_url', 'url_type' => 'vimeo', 'poster_field' => ''],
      ],
      default => NULL,
    });
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->willReturn($settings);

    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->method('get')->willReturnCallback(function (string $cid): object|false {
      $id = substr($cid, strlen('penn_global_metatags:vimeo:'));
      return isset($this->vimeoCache[$id]) ? (object) ['data' => $this->vimeoCache[$id]] : FALSE;
    });
    $vimeo = new VimeoMetadata(
      $this->createMock(ClientInterface::class),
      $config_factory,
      $cache,
      new RequestStack(),
      $this->createMock(LoggerInterface::class),
    );

    return new EmbeddedVideoResolver(
      $entity_type_manager,
      $config_factory,
      $vimeo,
      $this->createMock(FileUrlGeneratorInterface::class),
    );
  }

  /**
   * Creates a node whose body embeds the component under self::UUID.
   */
  protected function createNode(): NodeInterface {
    $body = $this->createField([
      'value' => '<p>An introduction to the University.</p><drupal-entity data-embed-button="penn_entity" data-entity-type="penn_entity" data-entity-uuid="' . self::UUID . '"></drupal-entity>',
    ]);
    $url = $this->createMock(Url::class);
    $url->method('toString')->willReturn('https://www.upenn.edu/about');

    $node = $this->createMock(NodeInterface::class);
    $this->configureCacheability($node);
    $node->method('hasField')->willReturnCallback(static fn(string $name): bool => $name === 'body');
    $node->method('get')->willReturn($body);
    $node->method('label')->willReturn('About Penn');
    $node->method('toUrl')->willReturn($url);
    $node->method('getCreatedTime')->willReturn(1735689600);

    return $node;
  }

  /**
   * Creates an embedded component mock with the given populated fields.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface[] $fields
   *   Fields keyed by machine name.
   */
  protected function createComponent(string $bundle, array $fields): FieldableEntityInterface {
    $entity = $this->createMock(FieldableEntityInterface::class);
    $this->configureCacheability($entity);
    $entity->method('bundle')->willReturn($bundle);
    $entity->method('hasField')->willReturnCallback(static fn(string $name): bool => isset($fields[$name]));
    $entity->method('get')->willReturnCallback(static fn(string $name): FieldItemListInterface => $fields[$name]);

    return $entity;
  }

  /**
   * Creates a populated field mock with magic property access.
   *
   * @param array<string, mixed> $properties
   *   Values returned for magic field properties.
   */
  protected function createField(array $properties): FieldItemListInterface {
    $field = $this->createMock(FieldItemListInterface::class);
    $field->method('isEmpty')->willReturn(FALSE);
    $field->method('__get')->willReturnCallback(static fn(string $name): mixed => $properties[$name] ?? NULL);
    $field->method('__isset')->willReturnCallback(static fn(string $name): bool => isset($properties[$name]));

    return $field;
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
