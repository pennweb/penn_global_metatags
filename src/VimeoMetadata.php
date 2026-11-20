<?php

namespace Drupal\penn_global_metatags;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Reads video metadata from Vimeo's oEmbed endpoint, cached.
 *
 * Domain-restricted videos only return metadata (duration, upload date,
 * thumbnail) to a whitelisted Referer; otherwise oEmbed answers 200 with just
 * the iframe markup. Set vimeo_referrer to the production URL so dev and test
 * environments get the same answer.
 *
 * Vimeo's titles are often upload slugs, so the title is deliberately not
 * returned.
 *
 * Results are cached so a page only calls Vimeo on a cache miss: a week for an
 * answer, an hour for a failure, so a Vimeo outage is not retried on every
 * request.
 */
class VimeoMetadata {

  /**
   * Matches the numeric ID in the URL shapes editors paste.
   */
  private const ID_PATTERN = '#(?:vimeo\.com/(?:video/|channels/[^/]+/|groups/[^/]+/videos/)?)(\d+)#i';

  private const CACHE_HIT_TTL = 604800;

  private const CACHE_MISS_TTL = 3600;

  public function __construct(
    protected ClientInterface $httpClient,
    protected ConfigFactoryInterface $configFactory,
    protected CacheBackendInterface $cache,
    protected RequestStack $requestStack,
    protected LoggerInterface $logger,
  ) {}

  /**
   * Returns a numeric Vimeo ID from a bare ID or any Vimeo URL.
   */
  public function extractId(string $value): ?string {
    $value = trim($value);
    if (preg_match('/^\d+$/', $value)) {
      return $value;
    }
    if (preg_match(self::ID_PATTERN, $value, $matches)) {
      return $matches[1];
    }

    return NULL;
  }

  /**
   * Returns the player URL used as the schema.org embedUrl.
   */
  public function getEmbedUrl(string $id): string {
    return 'https://player.vimeo.com/video/' . $id;
  }

  /**
   * Returns cached metadata for a video.
   *
   * @return array{duration?: int, upload_date?: string, thumbnail_url?: string, description?: string}
   *   Only the keys Vimeo answered with; empty when unknown.
   */
  public function getMetadata(string $id): array {
    $cid = 'penn_global_metatags:vimeo:' . $id;
    if ($cached = $this->cache->get($cid)) {
      return $cached->data;
    }

    $data = $this->fetch($id);
    $this->cache->set($cid, $data, time() + ($data ? self::CACHE_HIT_TTL : self::CACHE_MISS_TTL));

    return $data;
  }

  /**
   * Calls the oEmbed endpoint.
   */
  protected function fetch(string $id): array {
    $settings = $this->configFactory->get('penn_global_metatags.settings');
    $referrer = (string) $settings->get('vimeo_referrer');
    if ($referrer === '' && ($request = $this->requestStack->getCurrentRequest())) {
      $referrer = $request->getSchemeAndHttpHost() . '/';
    }

    try {
      $response = $this->httpClient->request('GET', 'https://vimeo.com/api/oembed.json', [
        'query' => ['url' => 'https://vimeo.com/' . $id],
        'headers' => $referrer !== '' ? ['Referer' => $referrer] : [],
        'timeout' => (int) ($settings->get('vimeo_timeout') ?: 3),
        'http_errors' => FALSE,
      ]);
    }
    catch (GuzzleException $e) {
      $this->logger->warning('Vimeo oEmbed request failed for @id: @message', [
        '@id' => $id,
        '@message' => $e->getMessage(),
      ]);
      return [];
    }

    if ($response->getStatusCode() !== 200) {
      $this->logger->warning('Vimeo oEmbed returned @status for @id.', [
        '@status' => $response->getStatusCode(),
        '@id' => $id,
      ]);
      return [];
    }

    $payload = json_decode((string) $response->getBody(), TRUE);
    if (!is_array($payload)) {
      return [];
    }

    return $this->normalize($payload);
  }

  /**
   * Keeps the oEmbed fields used for schema.org, in a stable shape.
   */
  public function normalize(array $payload): array {
    $data = [];

    if (isset($payload['duration']) && is_numeric($payload['duration']) && (int) $payload['duration'] > 0) {
      $data['duration'] = (int) $payload['duration'];
    }
    if (!empty($payload['upload_date']) && ($time = strtotime((string) $payload['upload_date'])) !== FALSE) {
      $data['upload_date'] = date('c', $time);
    }
    if (!empty($payload['thumbnail_url']) && preg_match('#^https://#i', (string) $payload['thumbnail_url'])) {
      $data['thumbnail_url'] = (string) $payload['thumbnail_url'];
    }
    if (!empty($payload['description'])) {
      $data['description'] = trim(strip_tags((string) $payload['description']));
    }

    return $data;
  }

}
