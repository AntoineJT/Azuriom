#!/usr/bin/env sh
#
# Shell Azuriom installer
#
# Originally written by Antoine James Tournepiche
# for Azuriom project
# https://github.com/Azuriom/Azuriom
#

echo "     _                    _                 "
echo "    / \    _____   _ _ __(_) ___  _ __ ___  "
echo "   / _ \  |_  / | | | '__| |/ _ \| '_ \` _ \ "
echo "  / ___ \  / /| |_| | |  | | (_) | | | | | |"
echo " /_/   \_\/___|\__,_|_|  |_|\___/|_| |_| |_|"
echo ""

sleep 0.5

echo "Azuriom simple installer"
echo "This is part of the Azuriom project"
echo "https://github.com/Azuriom/Azuriom"
echo ""

echo "Try to install dependencies using Composer..."
composer install || (printf "Composer not found!\nInstall it before to run Azuriom installer!\Exiting..." && exit)

echo "Creating default .env file..."
cp .env.example .env
echo "Generating application key..."
php artisan key:generate

echo ""
