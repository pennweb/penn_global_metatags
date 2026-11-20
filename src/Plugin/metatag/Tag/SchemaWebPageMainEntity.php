<?php

namespace Drupal\penn_global_metatags\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the schema.org WebPage 'mainEntity' meta tag.
 *
 * schema_web_page does not ship a mainEntity tag. Google requires it on a
 * ProfilePage, pointing at the Person or Organization the profile is about.
 *
 * @MetatagTag(
 *   id = "schema_web_page_main_entity",
 *   label = @Translation("mainEntity"),
 *   description = @Translation("The @id of the main entity the page is about, e.g. the Person on a profile page."),
 *   name = "mainEntity",
 *   group = "schema_web_page",
 *   weight = 6,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "text",
 *   tree_parent = {},
 *   tree_depth = -1,
 * )
 */
class SchemaWebPageMainEntity extends SchemaNameBase {

  /**
   * {@inheritdoc}
   *
   * Emits {"@id": ...} rather than a bare URL string, so search engines read
   * the value as a reference to another node in the JSON-LD graph instead of
   * plain text.
   */
  public function outputValue($input_value) {
    if (is_string($input_value) && $input_value !== '') {
      return ['@id' => $input_value];
    }

    return parent::outputValue($input_value);
  }

}
