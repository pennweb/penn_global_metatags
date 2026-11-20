<?php

namespace Drupal\penn_global_metatags;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Picks the image a page shares on social media.
 *
 * A node uses its own first image -- any image field, or any media reference,
 * whatever the content type calls it -- so no field names are assumed. Pages
 * without one fall back to the site's default image, which each site sets in
 * penn_global_metatags.settings rather than having one baked into the module.
 */
class SocialImage {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected RequestStack $requestStack,
  ) {}

  /**
   * Returns the site's default share image, or empty values when unset.
   *
   * @return array{url: string, alt: string}
   *   The absolute image URL and its alt text.
   */
  public function getSiteImage(BubbleableMetadata $bubbleable_metadata): array {
    $settings = $this->configFactory->get('penn_global_metatags.settings');
    $bubbleable_metadata->addCacheableDependency($settings);

    $url = $this->toAbsoluteUrl(trim((string) $settings->get('default_image')));

    return [
      'url' => $url,
      'alt' => $url !== '' ? trim((string) $settings->get('default_image_alt')) : '',
    ];
  }

  /**
   * Returns a node's own first image, falling back to the site default.
   *
   * @return array{url: string, alt: string}
   *   The absolute image URL and its alt text.
   */
  public function getNodeImage(NodeInterface $node, BubbleableMetadata $bubbleable_metadata): array {
    return $this->findImage($node, $bubbleable_metadata) ?? $this->getSiteImage($bubbleable_metadata);
  }

  /**
   * Finds the first populated image or media field on an entity.
   *
   * Fields are checked in their definition order, skipping base fields, so
   * the main image (usually the first one added) wins.
   *
   * @return array{url: string, alt: string}|null
   *   The image, or NULL when the entity has none.
   */
  public function findImage(FieldableEntityInterface $entity, BubbleableMetadata $bubbleable_metadata): ?array {
    foreach ($entity->getFieldDefinitions() as $name => $definition) {
      if ($definition->getFieldStorageDefinition()->isBaseField() || $entity->get($name)->isEmpty()) {
        continue;
      }

      $type = $definition->getType();
      $field = $entity->get($name);

      if ($type === 'image') {
        $file = $field->entity;
        if ($file instanceof FileInterface) {
          $bubbleable_metadata->addCacheableDependency($file);
          return [
            'url' => $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()),
            'alt' => trim((string) $field->alt),
          ];
        }
      }

      if ($type === 'entity_reference' && $definition->getSetting('target_type') === 'media') {
        // Every media item has a thumbnail, whatever its source, so this
        // covers image, video and document media alike.
        $media = $field->entity;
        if ($media instanceof FieldableEntityInterface && $media->hasField('thumbnail') && !$media->get('thumbnail')->isEmpty()) {
          $thumbnail = $media->get('thumbnail');
          $file = $thumbnail->entity;
          if ($file instanceof FileInterface) {
            $bubbleable_metadata->addCacheableDependency($media);
            $bubbleable_metadata->addCacheableDependency($file);
            return [
              'url' => $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()),
              'alt' => trim((string) $thumbnail->alt),
            ];
          }
        }
      }
    }

    return NULL;
  }

  /**
   * Resolves an absolute URL, site path or stream URI to an absolute URL.
   */
  public function toAbsoluteUrl(string $value): string {
    if ($value === '') {
      return '';
    }
    if (preg_match('#^https?://#i', $value)) {
      return $value;
    }
    if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $value)) {
      return $this->fileUrlGenerator->generateAbsoluteString($value);
    }

    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return '';
    }

    return $request->getSchemeAndHttpHost() . $request->getBasePath() . '/' . ltrim($value, '/');
  }

}
