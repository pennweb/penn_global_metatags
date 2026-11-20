<?php

namespace Drupal\penn_global_metatags;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Builds a schema.org BreadcrumbList from Drupal's breadcrumb.
 *
 * Drupal's breadcrumb stops at the parent page -- themes usually append the
 * current title only for display -- so schema_metatag's own breadcrumb tag
 * reads one item short, and top-level pages come out as a lone "Home" that
 * does not qualify for Google's breadcrumb display. This adds the current page
 * as the last item and returns nothing when the trail is still under two
 * items (e.g. the front page).
 */
class BreadcrumbTrail {

  public function __construct(
    protected BreadcrumbBuilderInterface $breadcrumbManager,
    protected TitleResolverInterface $titleResolver,
    protected RequestStack $requestStack,
    protected RendererInterface $renderer,
  ) {}

  /**
   * Returns the serialized BreadcrumbList for the current route, or ''.
   *
   * Serialized like other complex schema_metatag token values, which the
   * module unserializes before rendering.
   */
  public function build(RouteMatchInterface $route_match, BubbleableMetadata $bubbleable_metadata): string {
    $items = $this->getItems($route_match, $bubbleable_metadata);
    if (count($items) < 2) {
      return '';
    }

    $list = [];
    foreach (array_values($items) as $index => $item) {
      $list[] = [
        '@type' => 'ListItem',
        'position' => $index + 1,
        'name' => $item['name'],
        'item' => $item['url'],
      ];
    }

    return serialize([
      '@type' => 'BreadcrumbList',
      'itemListElement' => $list,
    ]);
  }

  /**
   * Collects name/url pairs: Drupal's trail, then the current page.
   *
   * @return array<int, array{name: string, url: string}>
   *   Trail items in order.
   */
  public function getItems(RouteMatchInterface $route_match, BubbleableMetadata $bubbleable_metadata): array {
    $breadcrumb = $this->breadcrumbManager->build($route_match);
    $bubbleable_metadata->addCacheableDependency($breadcrumb);
    // The trail and the current page item both vary by path.
    $bubbleable_metadata->addCacheContexts(['url.path']);

    $items = [];
    foreach ($breadcrumb->getLinks() as $link) {
      $name = $this->toPlainText($link->getText());
      $url = $link->getUrl()->setAbsolute()->toString();
      if ($name !== '' && $url !== '') {
        $items[] = ['name' => $name, 'url' => $url];
      }
    }

    $request = $this->requestStack->getCurrentRequest();
    $route = $route_match->getRouteObject();
    if (!$request || !$route) {
      return $items;
    }

    $title = $this->toPlainText($this->titleResolver->getTitle($request, $route));
    $current_url = $request->getSchemeAndHttpHost() . $request->getBaseUrl() . $request->getPathInfo();

    // Some breadcrumb builders already end on the current page; don't repeat
    // it.
    $last = end($items);
    if ($title !== '' && (!$last || rtrim($last['url'], '/') !== rtrim($current_url, '/'))) {
      $items[] = ['name' => $title, 'url' => $current_url];
    }

    return $items;
  }

  /**
   * Converts link text or a page title (string, markup or render array).
   */
  protected function toPlainText(mixed $text): string {
    if (is_array($text)) {
      $text = $this->renderer->renderInIsolation($text);
    }

    return trim(PlainTextOutput::renderFromHtml((string) $text));
  }

}
