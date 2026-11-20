<?php

namespace Drupal\penn_global_metatags\Plugin\metatag\Tag;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for a token-driven schema.org WebPage 'breadcrumb'.
 *
 * schema_web_page's own breadcrumb tag can only mirror Drupal's breadcrumb,
 * which leaves out the current page. This tag takes a serialized
 * BreadcrumbList instead -- see BreadcrumbTrail and the
 * [current-page:schema-breadcrumb] token. Do not enable both tags on one page,
 * as they render the same 'breadcrumb' property.
 *
 * @MetatagTag(
 *   id = "schema_web_page_breadcrumb_trail",
 *   label = @Translation("breadcrumb (token)"),
 *   description = @Translation("A serialized BreadcrumbList, e.g. [current-page:schema-breadcrumb]."),
 *   name = "breadcrumb",
 *   group = "schema_web_page",
 *   weight = 5,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "text",
 *   tree_parent = {},
 *   tree_depth = -1,
 * )
 */
class SchemaWebPageBreadcrumbTrail extends SchemaNameBase {

  /**
   * {@inheritdoc}
   *
   * Converts each value to trimmed plain text but never splits it on the
   * Metatag separator: page titles and topic names can contain commas, and
   * splitting one would break its ListItem apart.
   */
  protected function processItem(&$value, $key = 0) {
    $value = trim(PlainTextOutput::renderFromHtml((string) $value));
  }

  /**
   * {@inheritdoc}
   *
   * Values arrive as strings after token replacement; schema.org and Google
   * expect each ListItem position as a number. The list is returned directly:
   * the parent would only pass it through the 'text' property type, which
   * returns it unchanged.
   */
  public function outputValue($input_value) {
    if (is_array($input_value) && isset($input_value['itemListElement']) && is_array($input_value['itemListElement'])) {
      foreach ($input_value['itemListElement'] as &$item) {
        if (is_array($item) && isset($item['position']) && is_numeric($item['position'])) {
          $item['position'] = (int) $item['position'];
        }
      }
      unset($item);

      return $input_value;
    }

    return parent::outputValue($input_value);
  }

}
