<?php

/**
 * @file
 * Contains \Drupal\lucius_solr\Controller\luciusSolrController.
 */

namespace Drupal\lucius_solr\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\search_api_solr\SolrConnector\SolrConnectorPluginManager;
use Solarium\QueryType\Select\Query\Query;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\Xss;

/**
 * Class luciusSolrController.
 *
 * @package Drupal\lucius_solr\Controller
 */
class luciusSolrController extends ControllerBase {


  /**
   * The request.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * The backend plugin manager.
   *
   * @var \Drupal\search_api_solr\SolrConnector\SolrConnectorPluginManager
   */
  protected $solrConnectorPluginManager;

  /**
   * @var \Drupal\search_api_solr\SolrConnectorInterface
   */
  protected $solrConnector;

  /**
   * @var \Drupal\serialization\Encoder\JsonEncoder
   */
  protected $serialisation_service;

  /**
   * The backend configuration obtained from solr.
   *
   * @var array
   */
  protected $configuration;


  protected $entityManager;

  /**
   * Class constructor.
   *
   * @param RequestStack $request
   *   Request stack.
   */
  public function __construct(RequestStack $request, SolrConnectorPluginManager $solr_connector_plugin_manager, EntityManagerInterface $entityManager) {
    $this->request = $request;
    $this->solrConnectorPluginManager = $solr_connector_plugin_manager;
    $this->entityManager = $entityManager;

    // Fetch config from the solr search api module.
    $config = \Drupal::config('search_api.server.solr');

    // Fetch and store backend config.
    $this->configuration = $config->get('backend_config');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    return new static(
    // Load the service required to construct this class.
      $container->get('request_stack'),
      $container->get('plugin.manager.search_api_solr.connector'),
      $container->get('entity.manager')
    );
  }

  /**
   * Solr search callback.
   *
   * @param        $query_parameter
   *   The parameter to use.
   * @param string $processing_callback
   *   A callback for processing the returned documents.
   *
   * @return array
   *   Returns the found documents.
   *
   * @throws \Drupal\lucius_solr\Controller\SearchApiException
   */
  public function search($query_parameter, $processing_callback = '') {
    $response = [];

    // Fetch query from response.
    $query = $this->request->getCurrentRequest()->get($query_parameter);

    // Prepare query.
    $solr_query = $this->prepare_solr_query($query);

    // Create the connection to Solr.
    $connector = $this->getSolrConnector();

    $query = new Query();
    $query->setQuery($solr_query);
    $endpoint = $connector->getEndpoint();

    // Execute the query.
    $result = $connector->search($query, $endpoint);

    // Check if the query went well.
    if ($result->getStatusCode() == 200) {

      // Fetch the body and decode.
      $encoded_json = $result->getBody();
      $json = Json::decode($encoded_json);

      // Check if we have any documents.
      if ($json['response']['numFound'] > 0) {
        $references = array();

        // Default options.
        $options = ['absolute' => TRUE];

        // Fetch node storage.
        $node_storage = $this->entityManager->getStorage('node');

        foreach ($json['response']['docs'] as $doc) {

          // This is a referenced entity (for example paragraph).
          if (!empty($doc['ss_parent_id']) && !isset($references[$doc['ss_parent_id']])) {

            // Fetch node id.
            $node_id = $doc['ss_parent_id'][0];

            if (empty($processing_callback)) {

              // Load the node.
              $node = $node_storage->load($node_id);

              // Create reference for return.
              $references[$node_id] = array(
                'title' => $node->get('title')->value,
                'url' => Url::fromRoute('entity.node.canonical', ['node' => $node_id], $options),
              );
            }
            else {
              $references[$node_id] = $processing_callback($doc);
            }
          }
          elseif (!empty($doc['its_nid'])) {
            // Fetch node id.
            $node_id = $doc['its_nid'][0];

            if (empty($processing_callback)) {

              // Create reference for return.
              $references[$node_id] = array(
                'title' => $doc['ss_title'][0],
                'url'  => Url::fromRoute('entity.node.canonical', ['node' => $node_id], $options),
              );
            }
            else {
              $references[$node_id] = $processing_callback($doc);
            }
          }
        }
        $response = array_values($references);
      }
    }

    return $response;
  }

  /**
   * Callback function for processing docs.
   *
   * @param array $doc
   *   A document array as returned by SOLR.
   *
   * @return array|bool
   *   Returns either a processed array or a FALSE.
   */
  function lucius_solr_autocomplete_doc_processing($doc) {

    // Default options.
    $options = ['absolute' => TRUE];

    // Fetch node storage.
    $node_storage = $this->entityManager->getStorage('node');

    // This is a referenced entity (for example paragraph).
    if (!empty($doc['ss_parent_id']) && !isset($references[$doc['ss_parent_id']])) {

      // Fetch node id.
      $node_id = $doc['ss_parent_id'][0];

      // Load the node.
      $node = $node_storage->load($node_id);
      $title = $node->get('title')->value;
      $type = $node->getType();
      $label = node_get_type_label($node);

      // Create reference for return.
      return array(
        'value' => $title,
        'fields' => array(
          'title' => $title,
        ),
        'link' => Url::fromRoute('entity.node.canonical', ['node' => $node_id], $options)->toString(),
        'group' => array(
          'group_id' => $type,
          'group_name' => $label,
        ),
      );
    }
    elseif (!empty($doc['its_nid'])) {
      // Fetch node id.
      $node_id = $doc['its_nid'][0];

      // Load the node.
      $node = $node_storage->load($node_id);
      $type = $node->getType();
      $label = node_get_type_label($node);

      // Create reference for return.
      return array(
        'value' => $doc['ss_title'][0],
        'fields' => array(
          'title' => $doc['ss_title'][0],
        ),
        'link' => Url::fromRoute('entity.node.canonical', ['node' => $node_id], $options)->toString(),
        'group' => array(
          'group_id' => $type,
          'group_name' => $label,
        ),
      );
    }

    return FALSE;
  }

  /**
   * lucius_solr_autocomplete.
   *
   * @return string
   *   Return Hello string.
   */
  public function lucius_solr_autocomplete() {
    $response = [];

    // Fetch query from response.
    $query = $this->request->getCurrentRequest()->get('query');

    // Prepare query.
    $solr_query = $this->prepare_solr_query($query);

    // Create the connection to Solr.
    $connector = $this->getSolrConnector();

    $query = new Query();
    $query->setQuery($solr_query);
    $endpoint = $connector->getEndpoint();

    // Execute the query.
    $result = $connector->search($query, $endpoint);

    // Check if the query went well.
    if ($result->getStatusCode() == 200) {

      // Fetch the body and decode.
      $encoded_json = $result->getBody();
      $json = Json::decode($encoded_json);

      // Check if we have any documents.
      if ($json['response']['numFound'] > 0) {
        $references = array();

        // Default options.
        $options = ['absolute' => TRUE];

        // Fetch node storage.
        $node_storage = $this->entityManager->getStorage('node');

        /**
         * We have to filter the response as there can be multiple hits for
         * a single node and we don't want to flood the autocomplete.
         */
        foreach ($json['response']['docs'] as $doc) {

          // Don't flood the autocomplete.
          if (count($references) == 10) {
            break;
          }

          // This is a referenced entity (for example paragraph).
          if (!empty($doc['ss_parent_id']) && !isset($references[$doc['ss_parent_id']])) {

            // Fetch node id.
            $node_id = $doc['ss_parent_id'][0];

            // Load the node.
            $node = $node_storage->load($node_id);
            $title = $node->get('title')->value;
            $type = $node->getType();
            $label = node_get_type_label($node);

            // Create reference for return.
            $references[$node_id] = array(
              'value' => $title,
              'fields' => array(
                'title' => $title,
              ),
              'link' => Url::fromRoute('entity.node.canonical', ['node' => $node_id], $options)->toString(),
              'group' => array(
                'group_id' => $type,
                'group_name' => $label,
              ),
            );
          }
          elseif (!empty($doc['its_nid'])) {
            // Fetch node id.
            $node_id = $doc['its_nid'][0];

            // Load the node.
            $node = $node_storage->load($node_id);
            $type = $node->getType();
            $label = node_get_type_label($node);

            // Create reference for return.
            $references[$node_id] = array(
              'value' => $doc['ss_title'][0],
              'fields' => array(
                'title' => $doc['ss_title'][0],
              ),
              'link' => Url::fromRoute('entity.node.canonical', ['node' => $node_id], $options)->toString(),
              'group' => array(
                'group_id' => $type,
                'group_name' => $label,
              ),
            );
          }
          else {
            // No clues.
          }
        }
        $response = array_values($references);
      }
    }

    return new JsonResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  public function getSolrConnector() {
    if (!$this->solrConnector) {
      if (!($this->solrConnector = $this->solrConnectorPluginManager->createInstance($this->configuration['connector'], $this->configuration['connector_config']))) {
        throw new SearchApiException("The Solr Connector with ID '$this->configuration['connector']' could not be retrieved.");
      }
    }
    return $this->solrConnector;
  }

  /**
   * Private method for building the solr query.
   *
   * @param string $string
   *   The search string to be rebuild.
   *
   * @return string
   *   Returns the processed string.
   */
  private function prepare_solr_query($string) {
    $safe_query = Xss::filter($string);
    $rebuild = urldecode($safe_query) . '~0.6';
    return $rebuild;
  }
}
