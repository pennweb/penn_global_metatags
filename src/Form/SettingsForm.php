<?php

namespace Drupal\penn_global_metatags\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Settings for Penn Global Metatags: the default social sharing image.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'penn_global_metatags_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['penn_global_metatags.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('penn_global_metatags.settings');

    $form['default_image'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default sharing image'),
      '#default_value' => $config->get('default_image'),
      '#maxlength' => 2048,
      '#description' => $this->t('Shown when a page is shared on social media and has no image of its own. An absolute URL, a site path such as <code>/themes/custom/mytheme/share.jpg</code>, or <code>public://share.jpg</code>. Use a JPG or PNG at least 1200 × 630 pixels; social sites do not show SVG. Leave empty to share no image.'),
    ];

    $form['default_image_alt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Alt text'),
      '#default_value' => $config->get('default_image_alt'),
      '#maxlength' => 512,
      '#description' => $this->t('Describes the default image for people using screen readers.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $image = trim((string) $form_state->getValue('default_image'));
    if ($image !== '' && preg_match('/\.svg($|\?)/i', $image)) {
      $form_state->setErrorByName('default_image', $this->t('Social sites do not display SVG images. Use a JPG or PNG.'));
    }
    if ($image !== '' && !preg_match('#^(https?://|[a-z][a-z0-9+.-]*://|/)#i', $image)) {
      $form_state->setErrorByName('default_image', $this->t('Enter an absolute URL, a path starting with /, or a URI such as public://share.jpg.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('penn_global_metatags.settings')
      ->set('default_image', trim((string) $form_state->getValue('default_image')))
      ->set('default_image_alt', trim((string) $form_state->getValue('default_image_alt')))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
