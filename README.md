# Grafana Module for Icinga Web 2

#### Table of Contents

1. [About](#about)
2. [License](#license)
3. [Support](#support)
4. [Requirements](#requirements)
5. [Installation](#installation)
6. [Configuration](#configuration)
7. [FAQ](#faq)
8. [Thanks](#thanks)
9. [Contributing](#contributing)


## About

Add Grafana graphs into Icinga Web 2 to display performance metrics.

![Icinga Web 2 Grafana Integration](https://github.com/Mikesch-mp/icingaweb2-module-grafana/raw/master/doc/images/icingaweb2_grafana_screenshot_01.png "Grafana")
![Icinga Web 2 Grafana Integration](https://github.com/Mikesch-mp/icingaweb2-module-grafana/raw/master/doc/images/icingaweb2_grafana_screenshot_02.png "Grafana")

## License

Icinga Web 2 and this Icinga Web 2 module are licensed under the terms of the GNU
General Public License Version 2, you will find a copy of this license in the
LICENSE file included in the source package.

## Support

Join the [Icinga community channels](https://www.icinga.com/community/get-involved/) for questions.

## Requirements

* [Icinga Web 2](https://www.icinga.com/products/icinga-web-2/) (>= 2.4.1)
* [Grafana](https://grafana.com/) (>= 4.1)
* [InfluxDB](https://docs.influxdata.com/influxdb/), [Graphite](https://graphiteapp.org) or [PNP](https://docs.pnp4nagios.org/) (untested) as backend for Grafana
* [PHP](https://www.php.net) with curl enabled (for proxy mode)

## Installation

Extract this module to your Icinga Web 2 modules directory as `grafana` directory.

Git clone:

```
cd /usr/share/icingaweb2/modules
git clone https://github.com/Mikesch-mp/icingaweb2-module-grafana.git grafana
```

Tarball download (latest [release](https://github.com/Mikesch-mp/icingaweb2-module-grafana/releases/latest)):

```
cd /usr/share/icingaweb2/modules
wget https://github.com/Mikesch-mp/icingaweb2-module-grafana/archive/v1.1.0.zip
unzip v1.1.0.zip
mv icingaweb2-module-grafana-1.1.0 grafana
```

Enable the module in the Icinga Web 2 frontend in `Configuration -> Modules -> grafana -> enable`.
You can also enable the module by using the `icingacli` command:

```
icingacli module enable grafana
```

### Grafana Preparations

Enable basic auth or anonymous access in your Grafana configuration.

Choose which datasource to use (InfluxDB, Graphite). Import the JSON files from the `dashboards`
directory.

* `base-metrics.json`
* `icinga2-default.json`

The default dashboard name is 'icinga2-default'. You can also configure it inside the module.

There are currently no default dashboards for PNP available. Please create them on your own and send a PR.


## Configuration

### Global Configuration

You can edit global configuration settings in Icinga Web 2 in `Configuration -> Modules -> grafana -> Configuration`.

Setting            | Description
-------------------|-------------------
host               | **Required.** Grafana server host name (and port).
protocol           | **Optional.** Protocol used to access the Grafana server. Defaults to `http`.
graph height       | **Optional.** Graph height in pixel. Defaults to `280`.
graph width        | **Optional.** Graph width in pixel. Defaults to `640`.
timerange          | **Optional.** Global time range for graphs. Defaults to `6h`.
enableLink         | **Optional.** Enable/disable graph with a rendered URL to the Grafana dashboard. Defaults to `yes`.
datasource         | **Required.** Type of the Grafana datasource (`influxdb`, `graphite` or `pnp`). Defaults to `influxdb`.
defaultdashboard   | **Required.** Name of the default dashboard which will be shown for unconfigured graphs. Set to `none` to hide the module output. **Important: `panelID` must be set to `1`!** Defaults to `icinga2-default`.
defaultorgid       | **Required.** Name of the default organization id where dashboards are located. Defaults to `1`.
shadows            | **Optional.** Show shadows around the graphs. ** Defaults to `false`.
defaultdashboardstore | **Optional.** Grafana backend (file or database). Defaults to `Database`.
accessmode         | **Optional.** Controls whether graphs are fetched with curl (`proxy`), are embedded (`direct`) or in iframe ('iframe'. Direct access is faster and needs `auth.anonymous` enabled in Grafana. Defaults to `proxy`.
timeout            | **Proxy only** **Optional.** Timeout in seconds for proxy mode to fetch images. Defaults to `5`.
username           | **Proxy non anonymous only** **Required** HTTP Basic Auth user name to access Grafana.
password           | **Recommended** HTTP Basic Auth password to access Grafana, and key for encryption.
                     The password field data is also used during data encryption even when the http auth is disabled, so type a password even if you use anonymous auth.
directrefresh      | **Direct Only** **Optional.** Refresh graphs on direct access. Defaults to `no`.
usepublic          | **Optional** Enable usage of publichost/protocol. Defaults to `no`.
publichost         | **Optional** Use a diffrent host for the graph links.
publicprotocol     | **Optional** Use a diffrent protocol for the graph links.
custvardisable     | **Optional** Custom variable (vars.idontwanttoseeagraph for example) that will disable graphs. Defaults to `grafana_graph_disable`. 
theme              | **Optional.** Select grafana theme for the graph (light or dark). Defaults to `light`.

**IMPORTANT**
Be warned on 'iframe' access mode the auto refresh will hit you!

Example:
```
vim /etc/icingaweb2/modules/grafana/config.ini

[grafana]
username = "your grafana username"
host = "hostname:3000"
protocol = "https"
password = "123456"
height = "280"
width = "640"
timerange = "3h"
enableLink = "yes"
defaultdashboard = "icinga2-default"
shadows = "1"
datasource = "influxdb"
defaultdashboardstore = "db"
accessmode = "proxy"
timeout = "5"
directrefresh = "no"
usepublic = "no"
publichost = "otherhost:3000"
publicprotocol = "http"
custvardisable = "idontwanttoseeagraph"
```

### Graph Configuration

You can add specific graph configuration settings in Icinga Web 2 in `Configuration -> Grafana Graphs`.

Setting            | Description
-------------------|-------------------
name               | **Optional.** The name (not the `display_name`) of the service or check command where a graph should be rendered.
dashboard          | **Optional.** Name of the Grafana dashboard to use.
panelId            | **Optional.** Graph panelId. Open Grafana and select to share your dashboard to extract the value.
orgId              | **Optional.** Organization Id where the dashboard is located. Open Grafana and select to share your dashboard to extract the value.
customVars         | **Optional.** Set additional custom variables used for Grafana.
hostDashboard      | **Optional.** Dashboard for the link on host graph.
timerange          | **Optional.** Specify the time range for this graph.
height             | **Optional.** Graph height in pixel. Overrides global default.
width              | **Optional.** Graph width in pixel. Overrides global default.

Example:
```
vim /etc/icingaweb2/modules/grafana/graphs.ini

[check_command]
dashboard = "my-own"
panelId = "42"
orgId = "1"
customVars = "&os=$os$"
timerange = "3h"
height = "100"
width = "150"

```


## FAQ

### Search order

This module prefers the `service name`, then looks for an optional `parametrized service name` and for the `service check command name`.

If there is no match, it will use the default dashboard as fallback.

Example:

```
Service = "MySQL Users", check_command = mysql_health
```
At first glance `Name = "MySQL Usage"` must provide a match. Then `MySQL` and last but not least any service
`check_command` attribute which is set to `mysql_health`.

After the config section named with service or command is found, the module looks for the overrides specified to show graphs based on hostgroup and hostname, example:
Consider a graph with dashboard='Servers' and panelId='4'
Dashboard overrides:
```
'linux-servers'='Linux'
'windows-servers'='windows'
'server1'='dashboard-for-server1'
```

PanelId overrides:
```
'windows-servers'='6'
'linux-servers'='7'
'server1'='2'
```

First, the panelId "4" and dashboard "Servers" will be used. If the hostgroup matches "linux-servers", panelId "7" and dashboard "Linux" will be used. If the hostgroup matches "windows-servers", panelId "6" and dashboard "windows" will be used. And if the hostname matches "server1", the dashboard "dashboard-for-server1" and panelId "2" will be used. If no matches are found for hostgroup or hostname, the dashboard = "Servers" and panelId="4" will be used.



## Thanks

This module borrows a lot from https://github.com/Icinga/icingaweb2-module-generictts & https://github.com/Icinga/icingaweb2-module-pnp.

## Contributing

There are many ways to contribute to the Icinga Web module for Grafana --
whether it be sending patches, testing, reporting bugs, or reviewing and
updating the documentation. Every contribution is appreciated!

Please continue reading in the [contributing chapter](CONTRIBUTING.md).

