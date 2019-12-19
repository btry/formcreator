#!/bin/sh

install_plugin() {
    cp -r ../formcreator plugins/$PLUGINNAME
    cd plugins/$PLUGINNAME
    composer install
    vendor/bin/robo build:fa-data
    yarn install --non-interactive --prod
}

init_plugin() {
    : # nothing to initialize with this plugin
}

