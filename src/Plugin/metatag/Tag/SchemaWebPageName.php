<?php

namespace Drupal\penn_global_metatags\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the schema.org WebPage 'name' meta tag.
 *
 * schema_web_page does not ship a name tag. Without one the WebPage node in
 * the JSON-LD graph is anonymous, which validators flag as incomplete.
 *
 * @MetatagTag(
 *   id = "schema_web_page_name",
 *   label = @Translation("name"),
 *   description = @Translation("The name of the page."),
 *   name = "name",
 *   group = "schema_web_page",
 *   weight = 2,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "text",
 *   tree_parent = {},
 *   tree_depth = -1,
 * )
 */
class SchemaWebPageName extends SchemaNameBase {

}
