# Icinga Web 2 Netbox Import

Import data from [netbox](https://github.com/digitalocean/netbox)
into icinga2 with [director](https://github.com/Icinga/icingaweb2-module-director).

## Installation

```shell
$ cd /usr/share/icingaweb2/modules
$ git clone https://github.com/leprasmurf/icingaweb2-module-netboximport.git netboximport
$ icingacli module enable netboximport
```

## Configuration

All configuration is done in the web interface under the "Automation" tab of
icinga2 director. Please read to the [official documentation](https://www.icinga.com/docs/director/latest/doc/70-Import-and-Sync/)
before configuring a netbox import.

1. Add an "Import Source"
  * Key column name **Must be unique** (ex:  `name`)
  * Base URL (ex: https://nextbox.example.com)
  * API Path (ex: `/api/dcim/devices`)
  * API Token (see https://netbox.example.com/user/api-tokens/)
  * Import active objects only: y/n
2. Add any data modifiers desired / required
3. test the Import source via the "Check for changes" button, "Preview" tab and finally "Trigger Import Run"
4. Add a "Sync Rule" (specifics depend on the type of object to import)
  * Filter Expression to specify which data to import (ex:  `(site__slug=us1|site__slug=us2)`)
5. Add the desired Properties to the rule
  * setting `object_name`, `address` and `address6` to `name` is generally desirable
6. Test the Sync Rule via the "Check for changes" and finally "Trigger this Sync" buttons.
7. Add jobs to run the import and sync rules on a cadence.

## Data Format

This plugin pulls all available objects from the API path specified.  Since the data in netbox mostly consists of nested objects, all values are flatted (double underscore separated):

```yml
{
  "id": 39,
  "name": "3c09",
  "display_name": "3c09",
  "device_type": {
      "id": 19,
      "url": "https://netbox.example.com/api/dcim/device-types/19/",
      "manufacturer": {
          "id": 12,
          "url": "https://netbox.example.com/api/dcim/manufacturers/12/",
          "name": "3COM",
          "slug": "3com"
      },
      "model": "Baseline 2250-SPF-Plus",
      "slug": "baseline-2250-spf-plus"
  },
}
```

:arrow_right:

```yml
id: 39
name: 3c09
display_name: 3c09
device_type__id: 19
device_type__url: https://netbox.example.com/api/dcim/device-types/19/
device_type__manufacturer__id: 12
device_type__manufacturer__url: https://netbox.example.com/api/dcim/manufacturers/12/
...
```

A list of all possible fields can be seen in the "Preview" of your Import Source,
in your Sync Rule while adding a new property or in your API itself: https://netbox.example.com/api/dcim/devices/,
https://netbox.example.com/api/virtualization/virtual-machines/.

In some cases additional fields are provided:

* `cluster` is replaced by the actual cluster object as returned by the API,
  instead of just the id/name.
* all `id` and `url` sub-keys are removed to de-clutter the list.

## Acknowledgements

This is a fork of Uberspace's [icingaweb2-module-netboximport](https://github.com/Uberspace/icingaweb2-module-netboximport)

The general structure and a few tips were lifted from [icingaweb2-module-fileshipper](https://github.com/Icinga/icingaweb2-module-fileshipper).
Thanks!
