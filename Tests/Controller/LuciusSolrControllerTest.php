<?php

/**
 * @file
 * Contains \Drupal\lucius_solr\Tests\luciusSolrController.
 */

namespace Drupal\lucius_solr\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\Component\Serialization\Json;

/**
 * Provides automated tests for the lucius_solr module.
 */
class luciusSolrControllerTest extends WebTestBase {

  /**
   * Drupal\Component\Serialization\Json definition.
   *
   * @var Drupal\Component\Serialization\Json
   */
  protected $serialization_json;
  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => "lucius_solr luciusSolrController's controller functionality",
      'description' => 'Test Unit for module lucius_solr and controller luciusSolrController.',
      'group' => 'Other',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
  }

  /**
   * Tests lucius_solr functionality.
   */
  public function testluciusSolrController() {
    // Check that the basic functions of module lucius_solr.
    $this->assertEquals(TRUE, TRUE, 'Test Unit Generated via App Console.');
  }

}
