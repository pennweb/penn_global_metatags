<?php

namespace Drupal\Tests\penn_global_metatags\Unit;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Link;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\penn_global_metatags\BreadcrumbTrail;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;

/**
 * Tests the BreadcrumbList built from Drupal's breadcrumb.
 */
#[Group('penn_global_metatags')]
class BreadcrumbTrailTest extends UnitTestCase {

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
   * Tests that the current page is appended after Drupal's trail.
   */
  public function testTrailEndsWithCurrentPage(): void {
    $trail = $this->buildTrail(
      [['Home', 'https://www.upenn.edu/'], ['Academics', 'https://www.upenn.edu/academics']],
      'Undergraduate',
      'https://www.upenn.edu/academics/undergraduate',
    );

    $this->assertSame('BreadcrumbList', $trail['@type']);
    $this->assertSame(
      [
        [1, 'Home', 'https://www.upenn.edu/'],
        [2, 'Academics', 'https://www.upenn.edu/academics'],
        [3, 'Undergraduate', 'https://www.upenn.edu/academics/undergraduate'],
      ],
      $this->flatten($trail),
    );
  }

  /**
   * Tests that a top-level page becomes Home > page instead of a lone Home.
   */
  public function testTopLevelPageGetsTwoItems(): void {
    $trail = $this->buildTrail(
      [['Home', 'https://www.upenn.edu/']],
      'About',
      'https://www.upenn.edu/about',
    );

    $this->assertSame(
      [[1, 'Home', 'https://www.upenn.edu/'], [2, 'About', 'https://www.upenn.edu/about']],
      $this->flatten($trail),
    );
  }

  /**
   * Tests that a builder that already ends on the page is not doubled.
   */
  public function testCurrentPageIsNotRepeated(): void {
    $trail = $this->buildTrail(
      [['Home', 'https://www.upenn.edu/'], ['About', 'https://www.upenn.edu/about']],
      'About',
      'https://www.upenn.edu/about',
    );

    $this->assertCount(2, $trail['itemListElement']);
  }

  /**
   * Tests that the front page (no trail) yields no breadcrumb at all.
   */
  public function testFrontPageYieldsNothing(): void {
    $this->assertSame([], $this->buildTrail([], 'Home', 'https://www.upenn.edu/'));
  }

  /**
   * Builds and unserializes a trail for the given breadcrumb and page.
   *
   * @param array<int, array{0: string, 1: string}> $links
   *   Breadcrumb links as [text, absolute URL].
   */
  protected function buildTrail(array $links, string $title, string $current_url): array {
    $breadcrumb = new Breadcrumb();
    $breadcrumb->setLinks(array_map(fn(array $link): Link => new Link($link[0], $this->createUrl($link[1])), $links));

    $builder = $this->createMock(BreadcrumbBuilderInterface::class);
    $builder->method('build')->willReturn($breadcrumb);
    $titles = $this->createMock(TitleResolverInterface::class);
    $titles->method('getTitle')->willReturn($title);
    $requests = new RequestStack();
    $requests->push(Request::create($current_url));
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getRouteObject')->willReturn(new Route('/node/{node}'));

    $service = new BreadcrumbTrail($builder, $titles, $requests, $this->createMock(RendererInterface::class));
    $value = $service->build($route_match, new BubbleableMetadata());

    return $value === '' ? [] : unserialize($value, ['allowed_classes' => FALSE]);
  }

  /**
   * Reduces list items to [position, name, url] for compact assertions.
   */
  protected function flatten(array $trail): array {
    return array_map(
      static fn(array $item): array => [$item['position'], $item['name'], $item['item']],
      $trail['itemListElement'],
    );
  }

  /**
   * Creates an absolute URL mock.
   */
  protected function createUrl(string $url): Url {
    $mock = $this->createMock(Url::class);
    $mock->method('setAbsolute')->willReturnSelf();
    $mock->method('toString')->willReturn($url);

    return $mock;
  }

}
