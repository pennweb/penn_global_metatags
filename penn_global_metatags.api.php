<?php

/**
 * @file
 * Hooks provided by Penn Global Metatags.
 */

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\node\NodeInterface;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Changes the image a page shares on social media.
 *
 * $image starts as the site's default image from penn_global_metatags.settings
 * (empty when none is set). Use this to pick a node's own image, or to supply
 * a fallback for a group of sites. The URL may be absolute, a site path such
 * as /themes/custom/mytheme/share.jpg, or a stream URI such as
 * public://share.jpg; it is made absolute afterwards.
 *
 * @param array{url: string, alt: string} $image
 *   The image URL and alt text.
 * @param \Drupal\node\NodeInterface|null $node
 *   The node being shared, or NULL for [site:social-image].
 * @param \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata
 *   Add the cacheability of any entity the image comes from.
 */
function hook_penn_global_metatags_social_image_alter(array &$image, ?NodeInterface $node, BubbleableMetadata $bubbleable_metadata) {
  if ($node && $node->hasField('field_image') && !$node->get('field_image')->isEmpty()) {
    $file = $node->get('field_image')->entity;
    $bubbleable_metadata->addCacheableDependency($file);
    $image = [
      'url' => $file->getFileUri(),
      'alt' => (string) $node->get('field_image')->alt,
    ];
  }
}

/**
 * @} End of "addtogroup hooks".
 */
