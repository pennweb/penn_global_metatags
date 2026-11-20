<?php

namespace Drupal\Tests\penn_global_metatags\Unit;

use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the [site:social-image] tokens.
 */
#[Group('penn_global_metatags')]
class SocialImageTest extends UnitTestCase {

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
   * Tests that nodes get no image token of their own.
   */
  public function testNoNodeImageToken(): void {
    $this->setUpContainer('/share.jpg');

    $replacements = penn_global_metatags_tokens('node', ['social-image' => '[node:social-image]'], [], [], new BubbleableMetadata());

    $this->assertSame([], $replacements);
    $this->assertArrayNotHasKey('node', penn_global_metatags_token_info()['tokens']);
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
    \Drupal::setContainer($container);
  }

}
