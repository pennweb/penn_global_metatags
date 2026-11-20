<?php

namespace Drupal\penn_global_metatags\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the schema.org WebPage 'dateCreated' meta tag.
 *
 * schema_web_page does not ship a dateCreated tag. Recommended by Google on
 * ProfilePage.
 *
 * @MetatagTag(
 *   id = "schema_web_page_date_created",
 *   label = @Translation("dateCreated"),
 *   description = @Translation("When the page was created, in ISO 8601 format."),
 *   name = "dateCreated",
 *   group = "schema_web_page",
 *   weight = 7,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "text",
 *   tree_parent = {},
 *   tree_depth = -1,
 * )
 */
class SchemaWebPageDateCreated extends SchemaNameBase {

}
