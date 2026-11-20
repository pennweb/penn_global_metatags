<?php

namespace Drupal\penn_global_metatags\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the schema.org WebPage 'dateModified' meta tag.
 *
 * schema_web_page does not ship a dateModified tag. Recommended by Google on
 * ProfilePage.
 *
 * @MetatagTag(
 *   id = "schema_web_page_date_modified",
 *   label = @Translation("dateModified"),
 *   description = @Translation("When the page was last modified, in ISO 8601 format."),
 *   name = "dateModified",
 *   group = "schema_web_page",
 *   weight = 8,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "text",
 *   tree_parent = {},
 *   tree_depth = -1,
 * )
 */
class SchemaWebPageDateModified extends SchemaNameBase {

}
