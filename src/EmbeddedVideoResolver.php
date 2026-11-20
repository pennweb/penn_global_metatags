<?php

namespace Drupal\penn_global_metatags;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Turns a video component embedded in a node into schema.org VideoObject data.
 *
 * Which embedded entities count as video, and which fields hold the URL and
 * poster, come from penn_global_metatags.settings:video_sources, so any site
 * can map its own components. The first matching embed in the scanned fields
 * wins: a page gets one VideoObject, describing its main video.
 */
class EmbeddedVideoResolver {

  /**
   * Node tokens this resolver answers, without the "schema-video-" prefix.
   */
  public const TOKENS = [
    'type',
    'id',
    'name',
    'description',
    'thumbnail-url',
    'upload-date',
    'content-url',
    'embed-url',
    'duration',
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected VimeoMetadata $vimeoMetadata,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * Resolves [node:schema-video-*] tokens.
   *
   * @param string[] $names
   *   Token names, e.g. "schema-video-name".
   *
   * @return array<string, string>
   *   Values keyed by token name. Empty when the node has no video, so every
   *   VideoObject tag is dropped together rather than half-rendered.
   */
  public function resolveTokens(array $names, NodeInterface $node, BubbleableMetadata $bubbleable_metadata): array {
    $video = $this->getVideo($node, $bubbleable_metadata);
    if (!$video) {
      return [];
    }

    $values = [];
    foreach ($names as $name) {
      $key = substr($name, strlen('schema-video-'));
      if (isset($video[$key]) && $video[$key] !== '') {
        $values[$name] = (string) $video[$key];
      }
    }

    return $values;
  }

  /**
   * Finds the node's first embedded video and describes it.
   *
   * @return array<string, string|int>|null
   *   VideoObject values keyed like self::TOKENS, or NULL.
   */
  public function getVideo(NodeInterface $node, BubbleableMetadata $bubbleable_metadata): ?array {
    $settings = $this->configFactory->get('penn_global_metatags.settings');
    $bubbleable_metadata->addCacheableDependency($settings);

    $sources = (array) $settings->get('video_sources');
    if (!$sources) {
      return NULL;
    }

    foreach ((array) $settings->get('scan_fields') as $field_name) {
      if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
        continue;
      }

      foreach ($this->getEmbeds((string) $node->get($field_name)->value) as [$entity_type, $uuid]) {
        $entity = $this->loadEmbed($entity_type, $uuid);
        if (!$entity) {
          continue;
        }
        foreach ($sources as $source) {
          if (($source['entity_type'] ?? '') !== $entity_type || ($source['bundle'] ?? '') !== $entity->bundle()) {
            continue;
          }
          $video = $this->describe($source, $entity, $node, $bubbleable_metadata);
          if ($video) {
            return $video;
          }
        }
      }
    }

    return NULL;
  }

  /**
   * Lists [entity type, uuid] pairs for each <drupal-entity> embed, in order.
   *
   * @return array<int, array{0: string, 1: string}>
   *   Embeds found in the markup.
   */
  public function getEmbeds(string $html): array {
    if (!preg_match_all('/<drupal-entity\s+([^>]+)>/i', $html, $tags)) {
      return [];
    }

    $embeds = [];
    foreach ($tags[1] as $attributes) {
      if (preg_match('/data-entity-type="([a-z0-9_]+)"/i', $attributes, $type)
        && preg_match('/data-entity-uuid="([a-f0-9-]+)"/i', $attributes, $uuid)) {
        $embeds[] = [$type[1], $uuid[1]];
      }
    }

    return $embeds;
  }

  /**
   * Loads an embedded entity by UUID, tolerating unknown entity types.
   */
  protected function loadEmbed(string $entity_type, string $uuid): ?FieldableEntityInterface {
    try {
      $entities = $this->entityTypeManager->getStorage($entity_type)->loadByProperties(['uuid' => $uuid]);
    }
    catch (PluginException) {
      return NULL;
    }

    $entity = $entities ? reset($entities) : NULL;

    return $entity instanceof FieldableEntityInterface ? $entity : NULL;
  }

  /**
   * Builds the VideoObject values for one matched component.
   */
  protected function describe(array $source, FieldableEntityInterface $entity, NodeInterface $node, BubbleableMetadata $bubbleable_metadata): ?array {
    $url_field = (string) ($source['url_field'] ?? '');
    if ($url_field === '' || !$entity->hasField($url_field) || $entity->get($url_field)->isEmpty()) {
      return NULL;
    }

    $field = $entity->get($url_field);
    $raw = trim((string) ($field->uri ?? $field->value ?? ''));
    if ($raw === '') {
      return NULL;
    }

    $video = [
      'type' => 'VideoObject',
      'id' => $node->toUrl('canonical', ['absolute' => TRUE])->toString() . '#video',
      'name' => trim(strip_tags((string) $node->label())),
    ];
    $meta = [];

    if (($source['url_type'] ?? 'file') === 'vimeo') {
      $id = $this->vimeoMetadata->extractId($raw);
      if ($id === NULL) {
        return NULL;
      }
      $video['embed-url'] = $this->vimeoMetadata->getEmbedUrl($id);
      $meta = $this->vimeoMetadata->getMetadata($id);
    }
    else {
      $content_url = $this->toAbsoluteUrl($raw);
      if ($content_url === '') {
        return NULL;
      }
      $video['content-url'] = $content_url;
    }

    $bubbleable_metadata->addCacheableDependency($entity);

    $video['thumbnail-url'] = $this->getPosterUrl($entity, (string) ($source['poster_field'] ?? ''), $bubbleable_metadata)
      ?: ($meta['thumbnail_url'] ?? '');
    $video['upload-date'] = $meta['upload_date'] ?? $this->getCreatedDate($entity, $node);
    $video['description'] = ($meta['description'] ?? '') ?: $this->getNodeSummary($node);
    if (isset($meta['duration'])) {
      // Bare seconds: schema_metatag's Duration type renders ISO 8601 PT<n>S.
      $video['duration'] = $meta['duration'];
    }

    return $video;
  }

  /**
   * Resolves a link-field URI or plain URL to an absolute URL.
   */
  protected function toAbsoluteUrl(string $uri): string {
    if (preg_match('#^https?://#i', $uri)) {
      return $uri;
    }
    if (preg_match('#^[a-z]+://#i', $uri)) {
      // A stream wrapper such as public://.
      return $this->fileUrlGenerator->generateAbsoluteString($uri);
    }
    try {
      if (str_starts_with($uri, '/')) {
        $uri = 'base:' . ltrim($uri, '/');
      }
      return Url::fromUri($uri, ['absolute' => TRUE])->toString();
    }
    catch (\InvalidArgumentException) {
      return '';
    }
  }

  /**
   * Returns the absolute URL of a media poster's thumbnail image.
   *
   * Uses the media entity's own thumbnail, which every media type has, so the
   * poster can be any media type rather than one with a known image field.
   */
  protected function getPosterUrl(FieldableEntityInterface $entity, string $poster_field, BubbleableMetadata $bubbleable_metadata): string {
    if ($poster_field === '' || !$entity->hasField($poster_field) || $entity->get($poster_field)->isEmpty()) {
      return '';
    }

    $media = $entity->get($poster_field)->entity;
    if (!$media instanceof FieldableEntityInterface || !$media->hasField('thumbnail') || $media->get('thumbnail')->isEmpty()) {
      return '';
    }

    $file = $media->get('thumbnail')->entity;
    if (!$file || !method_exists($file, 'getFileUri')) {
      return '';
    }

    $bubbleable_metadata->addCacheableDependency($media);
    $bubbleable_metadata->addCacheableDependency($file);

    return $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
  }

  /**
   * Uses the component's creation date, falling back to the node's.
   */
  protected function getCreatedDate(FieldableEntityInterface $entity, NodeInterface $node): string {
    $timestamp = NULL;
    if ($entity->hasField('created') && !$entity->get('created')->isEmpty()) {
      $timestamp = (int) $entity->get('created')->value;
    }
    $timestamp = $timestamp ?: (int) $node->getCreatedTime();

    return $timestamp ? date('c', $timestamp) : '';
  }

  /**
   * Returns the node's summary, or the start of its body as plain text.
   */
  protected function getNodeSummary(NodeInterface $node): string {
    if (!$node->hasField('body') || $node->get('body')->isEmpty()) {
      return '';
    }

    $body = $node->get('body');
    $text = trim((string) ($body->summary ?? ''));
    if ($text === '') {
      $text = (string) $body->value;
    }
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

    return Unicode::truncate($text, 300, TRUE, TRUE);
  }

}
