<?php

namespace Icinga\Module\Netboximport\ProvidedHook\Director;

use Icinga\Application\Config;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Netboximport\Api;

// error_reporting(E_ALL);
// ini_set('max_execution_time', 600);

class ImportSource extends ImportSourceHook
{
    private $api;
    private $log_file;

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'baseurl', array(
            'label'       => $form->translate('Base URL'),
            'required'    => true,
            'description' => $form->translate(
                'API url for your instance, e.g. https://netbox.example.com:443'
            )
        ));

        $form->addElement('text', 'apipath', array(
            'label'       => $form->translate('API Path'),
            'required'    => true,
            'description' => $form->translate(
                'API path to pull from (/api/dcim/devices)'
            )
        ));

        $form->addElement('text', 'apitoken', array(
            'label'       => $form->translate('API Token'),
            'required'    => true,
            'description' => $form->translate(
                '(readonly) API token. See https://netbox.example.com/user/api-tokens/'
            )
        ));

        $form->addElement('YesNo', 'activeonly', array(
            'label'       => $form->translate('Import active objects only'),
            'description' => $form->translate('only load objects with status "active" (as opposed to "planned" or "offline")'),
        ));
    }

    // Pull objects from API
    private function fetchObjects($resource, $pagination)
    {
        // Only return active devices?
        $active_only = $this->getSetting('activeonly') === 'y';

        // Pull data from the API
        return $this->api->getResource($resource, $this->getDefaultKeyColumnName(), $active_only, $pagination);
    }

    /**
     * Returns an array containing importable objects
     *
     * @return [obj, obj, obj, ...]
     */
    public function fetchData($pagination = true)
    {
        // Initialize an empty array
        $objects = [];

        // Create the API object
        $this->api = new Api(
            $this->getSetting('baseurl'),
            $this->getSetting('apitoken')
        );

        return $this->fetchObjects($this->getSetting('apipath'), $pagination);
    }

    /**
    * Returns a list of all available columns
    *
    * @return array
    */
    public function listColumns()
    {
        // Grab the first page of results
        $results = $this->fetchData(false);

        // Grab array keys from the non-static variables present in the first results record
        $columns = array_keys(get_object_vars($results[0]));

        return $columns;
    }

    /**
     * @inheritdoc
     */
    public static function getDefaultKeyColumnName()
    {
        return 'name';
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'Netbox';
    }
}
