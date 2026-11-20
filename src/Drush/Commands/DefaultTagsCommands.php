<?php

namespace Drupal\penn_global_metatags\Drush\Commands;

use Drupal\penn_global_metatags\DefaultTags;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Re-applies the module's metatag defaults on an existing site.
 */
final class DefaultTagsCommands extends DrushCommands {

  public function __construct(
    private readonly DefaultTags $defaultTags,
  ) {
    parent::__construct();
  }

  public static function create(ContainerInterface $container): self {
    return new self($container->get('penn_global_metatags.default_tags'));
  }

  /**
   * Adds any of the module's schema.org tags missing from metatag defaults.
   *
   * Safe to re-run: tags a site has already set are left as they are. Export
   * config afterwards to keep the change.
   */
  #[CLI\Command(name: 'penn-global-metatags:apply-defaults', aliases: ['pgm-defaults'])]
  #[CLI\Usage(name: 'drush pgm-defaults && drush cex', description: 'Add missing tags, then export config.')]
  public function applyDefaults(): int {
    $added = $this->defaultTags->apply();
    if (!$added) {
      $this->logger()->success('All schema.org tags are already set.');
      return self::EXIT_SUCCESS;
    }

    foreach ($added as $id => $names) {
      $this->logger()->success(dt('@id: added @tags', ['@id' => $id, '@tags' => implode(', ', $names)]));
    }

    return self::EXIT_SUCCESS;
  }

}
