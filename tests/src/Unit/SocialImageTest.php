<?php

namespace Drupal\Tests\penn_global_metatags\Unit;

use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the [site:social-image] and [node:social-image] tokens.
 */
#[Group('penn_global_metatags')]
class SocialImageTest extends UnitTestCase {

  /**
   * Stands in for hook_penn_global_metatags_social_image_alter(), if set.
   *
   * @var callable|null
   */
  protected $alter = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once __DIR__ . '/../../../penn_global_metatags.module';
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
      'private file' => ['private://share.jpg', ''],
      'temporary file' => ['temporary://share.jpg', ''],
      'unregistered scheme' => ['pubic://share.jpg', ''],
    ];
  }

  /**
   * Tests resolving the configured image to an absolute URL, with its alt.
   */
  #[DataProvider('imageValues')]
  public function testSiteImageResolvesToAbsoluteUrl(string $value, string $expected): void {
    $this->setUpContainer($value);

    $replacements = penn_global_metatags_tokens('site', [
      'social-image' => '[site:social-image]',
      'social-image-alt' => '[site:social-image-alt]',
    ], [], [], new BubbleableMetadata());

    $this->assertSame($expected, $replacements['[site:social-image]']);
    // No image means no alt text either.
    $this->assertSame($expected === '' ? '' : 'Campus at dusk', $replacements['[site:social-image-alt]']);
  }

  /**
   * Tests that a node shares the site default when no module changes it.
   */
  public function testNodeUsesSiteDefault(): void {
    $this->setUpContainer('/share.jpg');

    $replacements = $this->nodeTokens($this->createMock(NodeInterface::class));

    $this->assertSame('https://www.example.edu/share.jpg', $replacements['[node:social-image]']);
    $this->assertSame('Campus at dusk', $replacements['[node:social-image-alt]']);
  }

  /**
   * Tests that the alter hook can swap in a node's image, given as a URI.
   */
  public function testAlterSetsNodeImage(): void {
    $this->setUpContainer('/share.jpg');
    $node = $this->createMock(NodeInterface::class);
    $seen = NULL;
    $this->alter = static function (array &$image, ?NodeInterface $hook_node) use (&$seen): void {
      $seen = $hook_node;
      $image = ['url' => 'public://story.jpg', 'alt' => 'Students on Locust Walk'];
    };

    $replacements = $this->nodeTokens($node);

    $this->assertSame($node, $seen);
    $this->assertSame('https://www.example.edu/sites/default/files/story.jpg', $replacements['[node:social-image]']);
    $this->assertSame('Students on Locust Walk', $replacements['[node:social-image-alt]']);
  }

  /**
   * Tests that a private file chosen by the alter hook is not shared.
   */
  public function testAlterCannotSharePrivateFile(): void {
    $this->setUpContainer('/share.jpg');
    $this->alter = static function (array &$image): void {
      $image = ['url' => 'private://draft.jpg', 'alt' => 'Draft'];
    };

    $replacements = $this->nodeTokens($this->createMock(NodeInterface::class));

    $this->assertSame('', $replacements['[node:social-image]']);
    $this->assertSame('', $replacements['[node:social-image-alt]']);
  }

  /**
   * Tests that the alter hook can fill in an image when the site has none.
   */
  public function testAlterSuppliesFallbackForSiteToken(): void {
    $this->setUpContainer('');
    $this->alter = static function (array &$image, ?NodeInterface $node): void {
      if ($image['url'] === '' && $node === NULL) {
        $image = ['url' => '/themes/custom/penn_global/share.jpg', 'alt' => 'Penn Logo'];
      }
    };

    $replacements = penn_global_metatags_tokens('site', ['social-image' => '[site:social-image]'], [], [], new BubbleableMetadata());

    $this->assertSame('https://www.example.edu/themes/custom/penn_global/share.jpg', $replacements['[site:social-image]']);
  }

  /**
   * Replaces the node image tokens for a node.
   */
  protected function nodeTokens(NodeInterface $node): array {
    return penn_global_metatags_tokens('node', [
      'social-image' => '[node:social-image]',
      'social-image-alt' => '[node:social-image-alt]',
    ], ['node' => $node], [], new BubbleableMetadata());
  }

  /**
   * Registers the services the token hook uses.
   */
  protected function setUpContainer(string $default_image): void {
    $settings = $this->createMock(ImmutableConfig::class);
    $settings->method('get')->willReturnMap([
      ['default_image', $default_image],
      ['default_image_alt', 'Campus at dusk'],
    ]);
    $settings->method('getCacheContexts')->willReturn([]);
    $settings->method('getCacheTags')->willReturn(['config:penn_global_metatags.settings']);
    $settings->method('getCacheMaxAge')->willReturn(-1);

    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('penn_global_metatags.settings')->willReturn($settings);

    $file_url_generator = $this->createMock(FileUrlGeneratorInterface::class);
    $file_url_generator->method('generateAbsoluteString')
      ->willReturnCallback(static fn(string $uri): string => 'https://www.example.edu/sites/default/files/' . substr($uri, strlen('public://')));

    $request_stack = new RequestStack();
    $request_stack->push(Request::create('https://www.example.edu/node/1'));

    // Cache::mergeContexts() asserts context tokens through the container.
    $contexts = $this->createMock(CacheContextsManager::class);
    $contexts->method('assertValidTokens')->willReturn(TRUE);

    $container = new ContainerBuilder();
    $container->set('config.factory', $config_factory);
    $container->set('file_url_generator', $file_url_generator);
    $container->set('request_stack', $request_stack);
    $container->set('cache_contexts_manager', $contexts);
    $container->set('module_handler', $this->createModuleHandler());
    $stream_wrappers = $this->createMock(StreamWrapperManagerInterface::class);
    $stream_wrappers->method('isValidScheme')->willReturnCallback(static fn(string $scheme): bool => in_array($scheme, ['public', 'private', 'temporary'], TRUE));
    $container->set('stream_wrapper_manager', $stream_wrappers);
    \Drupal::setContainer($container);
  }

  /**
   * Creates a module handler whose alter() runs $this->alter.
   */
  protected function createModuleHandler(): ModuleHandler {
    $test = $this;
    return new class($test) extends ModuleHandler {

      public function __construct(protected SocialImageTest $test) {}

      public function alter($type, &$data, &$context1 = NULL, &$context2 = NULL) {
        if ($type === 'penn_global_metatags_social_image' && $this->test->getAlter()) {
          ($this->test->getAlter())($data, $context1, $context2);
        }
      }

    };
  }

  /**
   * Returns the stand-in alter hook.
   */
  public function getAlter(): ?callable {
    return $this->alter;
  }

}
