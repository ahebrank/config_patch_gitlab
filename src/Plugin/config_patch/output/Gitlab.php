<?php

namespace Drupal\config_patch_gitlab\Plugin\config_patch\output;

use Drupal\config_patch\Plugin\config_patch\output\OutputPluginInterface;
use Drupal\config_patch\Plugin\config_patch\output\OutputPluginBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Simple text output of the patches.
 *
 * @ConfigPatchOutput(
 *  id = "config_patch_output_gitlab",
 *  label = @Translation("Create Gitlab MR by email"),
 *  action = @Translation("Email patch to create MR")
 * )
 */
class Gitlab extends OutputPluginBase implements OutputPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * Inject dependencies.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, MailManagerInterface $mail_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->mailManager = $mail_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('plugin.manager.mail')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function output(array $patches) {
    $config = $this->configFactory->get('config_patch_gitlab.settings');
    $to = $config->get('email');
    if (!$to) {
      throw new NotFoundHttpException();
    }

    // @TODO: inject user service.
    $current_user = \Drupal::currentUser();
    $email_ident = $current_user->getDisplayName() . '<' . $current_user->getEmail() . '>';
    $params['ident'] = $email_ident;

    $module = 'config_patch_gitlab';
    $key = 'send_patch';

    $params['attachments'] = [];
    $config_names = [];

    // Save out and attach each patch.
    $patch_count = count($patches);
    foreach ($patches as $i => $collection_patches) {
      $config_count = count($collection_patches);
      $output = "";
      foreach ($collection_patches as $config_name => $patch) {
        $output .= $patch;
        $config_names[] = $config_name;
      }

      $output_hash = sha1($output);
      $date = date('r');
      $branch_name = "config-patch-" . substr($output_hash, 0, 7);
      $patch_id = sprintf('%d/%d', $i + 1, $patch_count);

      // Add `git am` expected header on the patch itself.
      $patch_header = <<<HEADER
From $output_hash Mon Sep 17 00:00:00 2001
From: $email_ident
Date: $date
Subject: [PATCH $patch_id] Changes to $config_count configuration items

HEADER;

      $output = $patch_header . "\n" . $output;

      $fn = file_unmanaged_save_data($output);
      $file = new \stdClass();
      $file->uri = $fn;
      $file->filename = 'config-' . $i . '.patch';
      $file->filemime = 'text/plain';
      $params['attachments'][] = $file;
    }

    $params['message'] = "Alters config: \n" .
      implode("\r\n", array_map(function ($name) {
        return " - " . $name;
      }, $config_names));
    if ($suffix = $config->get('append_message')) {
      $params['message'] .= "\n\n" . $suffix;
    }
    $params['subject'] = $branch_name;

    $langcode = $current_user->getPreferredLangcode();
    $result = $this->mailManager->mail($module, $key, $to, $langcode, $params, NULL, TRUE);
    $messenger = \Drupal::messenger();
    if ($result['result']) {
      $message = 'Sent patch.';
      if ($url = $config->get('mr_list_link')) {
        $message .= ' <a href="' . $url . '">View merge requests.</a>';
      }
      $messenger->addStatus([
        '#markup' => $message,
      ]);
    }
  }

}
