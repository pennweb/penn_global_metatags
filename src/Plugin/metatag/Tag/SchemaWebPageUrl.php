<?php

namespace Drupal\penn_global_metatags\Plugin\metatag\Tag;

use Drupal\schema_metatag\Plugin\metatag\Tag\SchemaNameBase;

/**
 * Provides a plugin for the schema.org WebPage 'url' meta tag.
 *
 * schema_web_page does not ship a url tag. Gives the WebPage node an explicit
 * canonical URL alongside its @id.
 *
 * @MetatagTag(
 *   id = "schema_web_page_url",
 *   label = @Translation("url"),
 *   description = @Translation("The canonical URL of the page."),
 *   name = "url",
 *   group = "schema_web_page",
 *   weight = 3,
 *   type = "string",
 *   secure = FALSE,
 *   multiple = FALSE,
 *   property_type = "text",
 *   tree_parent = {},
 *   tree_depth = -1,
 * )
 */
class SchemaWebPageUrl extends SchemaNameBase {

}
