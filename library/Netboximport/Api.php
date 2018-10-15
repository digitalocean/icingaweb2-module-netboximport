<?php

namespace Icinga\Module\Netboximport;

use Icinga\Module\Director\Objects\IcingaObject;

class Api
{
    public function __construct($baseurl, $apitoken)
    {
        $this->baseurl = rtrim($baseurl, '/') . '/';
        $this->log_file = '/tmp/netbox_api.log';
        $this->ch = curl_init(); // curl handle

        // Configure curl
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true); // curl_exec returns response as a string
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirect requests
        curl_setopt($this->ch, CURLOPT_MAXREDIRS, 5); // limit number of redirects to follow
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Token ' . $apitoken,
        ));

        // Debug logging
        $this->log_file = fopen($this->log_file, "a");
    }

    // Debug logging
    private function log_msg($msg)
    {
        fwrite($this->log_file, $msg);
    }

    // src:  https://stackoverflow.com/a/9546235/2486196
    // adapted to also flatten nested stdClass objects
    public function flattenNestedArray($prefix, $array, $delimiter="__")
    {
        // Initialize empty array
        $result = [];

        // Cycle through input array
        foreach ($array as $key => $value) {
            // Element is an object instead of a value
            if (is_object($value)) {
                // Convert value to an associative array of public object properties
                $value = get_object_vars($value);
            }

            if (is_array($value)) {
                // Recursion
                $result = array_merge($result, $this->flattenNestedArray($prefix . $key . $delimiter, $value, $delimiter));
            } else {
                // no Recursion
                $result[$prefix . $key] = $value;
            }
        }

        return $result;
    }

    // returns json parsed object from GET request
    private function apiGet($url_path, $active_only, $get_params = [])
    {
        // Strip '/api' since it's included in $this->baseurl
        $url_path = preg_replace("#^/api/#", "/", $url_path);

        // Convert parameters to URL-encoded query string
        $query = http_build_query($get_params);

        // Tie it all together
        $uri = $this->baseurl . $url_path . '/?' . $query;

        // get rid of duplicate slashes
        $uri = preg_replace("#//#", "/", $uri);

        $this->log_msg("Target URI: $uri\n");

        // Update curl handler with new URI
        curl_setopt($this->ch, CURLOPT_URL, $uri);

        // Execute query
        // CURLOPT_RETURNTRANSFER forces the return to be a string
        $response = curl_exec($this->ch);

        $curl_error = curl_error($this->ch);
        $status = curl_getinfo($this->ch, CURLINFO_RESPONSE_CODE);

        // If the request was successful and no errors are present
        if ($curl_error === '' && $status === 200) {
            // Decode the JSON object and return the results
            $response = json_decode($response);

            return $response;
        } else {
            // Otherwise throw the error
            throw new \Exception("Netbox API request failed: uri=$uri; status=$status; error=$curl_error");
        }
    }

    // Parse get parameters or return the defaults
    private function parseGetParams($get_params = [])
    {
        $return_params = [
            "limit" => "1000" // matches netbox limit - https://github.com/digitalocean/netbox/blob/develop/docs/configuration/optional-settings.md#max_page_size
        ];

        // No get parameters set yet
        if ($get_params === []) {
            return $return_params;
        } elseif (is_string($get_params)) {
            // get parameters is currently in string format from `parse_url`
            // should be in the form of key=value&key2=value&key3=value
            $get_params = explode('&', $get_params);

            foreach ($get_params as $elements) {
                // Break "key=value" into array
                $tmp_array = explode('=', $elements);

                // Save to the return array
                $return_params[$tmp_array[0]] = $tmp_array[1];
            }
        } else {
            $return_params = array_merge($return_params, $get_params);
        }

        return $return_params;
    }

    // Query API for resource passed
    public function getResource($resource, $key_column, $active_only = 0, $pagination = true)
    {
        $results = [];

        // Pagination loop
        do {
            // Parse URL and assign query if set
            $resource = parse_url($resource);

            // Parse existing query or initialize empty array
            $query = $this->parseGetParams($resource['query'] ?? []);

            // Add the "active only" preference to the query
            $query["status"] = "$active_only";

            // Save page results to working list for processing before appending to $results
            $working_list = $this->apiGet($resource['path'], $active_only, $query);

            // Grab the next URL if it exists
            $resource = $working_list->next ?? null;

            // Break loop if pagination is false (returns one page)
            if ($pagination === false) {
                $resource = null;
                $this->log_msg("Pagination explicitly disabled.\n");
            }

            // Set the working list to results if multiple objects returned
            $working_list = $working_list->results ?? $working_list;

            $this->log_msg("Filtering Working list (" . count($working_list) . ") records that have an empty \"$key_column\" field\n");

            // Filter object missing the key column
            $working_list = array_filter($working_list, function ($obj) use ($key_column) {
                // remove null objects
                if ($obj === null) {
                    return false;
                }
                if (isset($obj->$key_column) && $obj->$key_column !== '') {
                    // keep objects that have a defined key column
                    return true;
                } else {
                    // remove otherwise
                    return false;
                }
            });

            // Work the objects into the results array keyed to the object ID
            foreach ($working_list as $obj) {
                // Flatten multi-dimensional array before pushing into results
                $flat_object = $this->flattenNestedArray('', $obj);

                // Push typecast object to results array
                $results[] = (object) $flat_object;
            }

            $this->log_msg("Current result count: " . count($results) . "\n\n");
        } while ($resource !== null);

        fclose($this->log_file);
        return $results;
    }
}
