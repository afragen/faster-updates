# Faster Updates

Plugin Name: Faster Updates
Contributors: afragen, costdev, pbiron
License: MIT
Requires PHP: 5.6
Requires at least: 6.0
Tested up to: 6.2
Stable Tag: x.x.x

Speeds up plugin/theme updates by moving files rather than copying them.

## Description

For testing only. Only works when updating from update-core.php page.

Speeds up plugin/theme updates by moving files rather than copying them. Reduces the chance of running out of diskspace during updates. Lower memory usage reduces the chance of timeouts during updates.

VirtualBox not supported -- yet??.

Substitution of move_dir for copy_dir adding more efficiency to the plugin/theme update process. This could improve the efficiency and performance for 99+% of users who opt-in and will likely fix #53832, #54166, and #34676.

## Changelog

#### x.x.x / 2023-01-xx
* initial commit
