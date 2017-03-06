<?php
/**
 * Ohana Client
 *
 * @copyright 2015 Michael Slone <m.slone@gmail.com>
 * @license http://opensource.org/licenses/MIT MIT
 * @package Omeka\Plugins\OhanaClient
 */

class OhanaClientPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
        'install',
        'config_form',
        'config',
        'uninstall',
        'before_save_item',
    );

    public function hookInstall()
    {
        $config = parse_ini_file(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.ini');
        set_option('ohana_library_path', $config['ohana_library_path']);
    }

    public function hookUninstall()
    {
        delete_option('ohana_library_path');
    }

    public function hookConfig($args)
    {
        set_option('ohana_library_path', trim($args['post']['ohana_library_path']));
    }

    public function hookConfigForm()
    {
        require dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config_form.php';
    }

    # Ensure that interviews have an accession number.
    #
    # The interview must be assigned to a collection to get an accession
    # number.  The following cases are supported:
    #
    # 1. interview < series < collection (the only documented membership)
    # 2. interview < collection
    #
    # More indirect relationships between interviews and collections are
    # not supported.
    public function hookBeforeSaveItem($args)
    {
        $item = $args['record'];
        if ($item->getItemType()->name !== 'interviews') {
            return;
        }

        $accession = metadata($item, array('Dublin Core', 'Identifier'));
        if (empty($accession)) {
            $elementSetName = 'Dublin Core';
            $elementName = 'Identifier';

            $parents = array();
            $post = $args['post'];
            $is_part_of = 7;
            if ($args['insert']) {
                foreach ($post['item_relations_property_id'] as $key => $propertyId) {
                    if (intval($propertyId) === $is_part_of) {
                        $parent_id = $post['item_relations_item_relation_object_item_id'][$key];
                        $parents[] = get_record_by_id('Item', $parent_id);
                    }
                }
            }
            else {
                foreach ($post['item_relations_property_id'] as $key => $propertyId) {
                    if (intval($propertyId) === $is_part_of) {
                        $parent_id = $post['item_relations_item_relation_object_item_id'][$key];
                        $parents[] = get_record_by_id('Item', $parent_id);
                    }
                }
                $subjects = get_db()->getTable('ItemRelationsRelation')->findBySubjectItemId($item->id);
                foreach ($subjects as $subject) {
                    if ($subject->getPropertyText() !== "Is Part Of") {
                        continue;
                    }
                    if (!($parent = get_record_by_id('item', $subject->object_item_id))) {
                        continue;
                    }
                    $parents[] = $parent;
                }
            }

            if (count($parents) > 0) {
                $type = 'oh';
                $year = date('Y');
                $candidates = array();

                $parent = $parents[0];
                if ($parent->getItemType()->name === 'collections') {
                    $candidates[] = $parent;
                }
                else {
                    $subjects = get_db()->getTable('ItemRelationsRelation')->findBySubjectItemId($parent->id);
                    foreach ($subjects as $subject) {
                        if ($subject->getPropertyText() !== "Is Part Of") {
                            continue;
                        }
                        if (!($candidate = get_record_by_id('item', $subject->object_item_id))) {
                            continue;
                        }
                        if ($candidate->getItemType()->name !== 'collections') {
                            continue;
                        }
                        $candidates[] = $candidate;
                    }
                }

                if (count($candidates) > 0) {
                    $candidate = $candidates[0];
                    $collection_accession = metadata($candidate, array('Dublin Core', 'Identifier'));
                    if (preg_match('/oh(.*)/i', $collection_accession, $matches)) {
                        $collection = trim(strtolower($matches[1]));
                        require_once(get_option('ohana_library_path'));
                        $response = mintAccessionNumber($type, $year, $collection);
                        if (array_key_exists('error', $response)) {
                            $accession = $response['error'];
                        }
                        else {
                            $accession = $response['canonical'];
                        }
                    }
                }

            }

            if ($accession) {
                $element = $item->getElement($elementSetName, $elementName);
                $item->addTextForElement($element, $accession, false);
            }
        }
    }
}
