<?php

use Bga\Games\Cardia\objects\CardiaCardInfo;
use Bga\Games\Cardia\objects\Faction;
use Bga\Games\Cardia\objects\PowerType;

/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * Cardia implementation : © Séverine Kamycki <mizutismask@gmail.com>
 * 
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * material.inc.php
 *
 * Cardia game material description
 *
 * Here, you can describe the material of your game with PHP variables.
 *   
 * This file is loaded in your game logic class constructor, ie these variables
 * are available everywhere in your game logic code.
 *
 */


$this->CARDIA_CARDS = [
  1 => [
    //deck 1
    1  => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::GREEN, self::_("Hired Blade")),
    2  => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::YELLOW, self::_("Void Mage")),
    3  => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::RED, self::_("Surgeon")),
    4  => new CardiaCardInfo(3, PowerType::ONGOING, Faction::BLUE, self::_("Mediator")),
    5  => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::GREEN, self::_("Saboteur")),
    6  => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::YELLOW, self::_("Fortune Teller")),
    7  => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::RED, self::_("Palace Guard")),
    8  => new CardiaCardInfo(3, PowerType::ONGOING, Faction::BLUE, self::_("Judge")),
    9  => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::GREEN, self::_("Ambusher")),
    10 => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::YELLOW, self::_("Puppeteer")),
    11 => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::RED, self::_("Clockmaker")),
    12 => new CardiaCardInfo(3, PowerType::ONGOING, Faction::BLUE, self::_("Treasurer")),
    13 => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::GREEN, self::_("Swamp Guardian")),
    14 => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::YELLOW, self::_("Magistra")),
    15 => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::RED, self::_("Inventor")),
    16 => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::BLUE, self::_("DJinn")),
  ],
  2 => [
    //deck 2
    1  => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::GREEN, self::_("Poisoner")),
    2  => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::YELLOW, self::_("Kinesis Mage")),
    3  => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::RED, self::_("Envoy")),
    4  => new CardiaCardInfo(3, PowerType::ONGOING, Faction::BLUE, self::_("Tax Collector")),
    5  => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::GREEN, self::_("Revolutionary")),
    6  => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::YELLOW, self::_("Librarian")),
    7  => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::RED, self::_("Prodigy")),
    8  => new CardiaCardInfo(3, PowerType::ONGOING, Faction::BLUE, self::_("Aristocrat")),
    9  => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::GREEN, self::_("Blackmailer")),
    10 => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::YELLOW, self::_("Illusionist")),
    11 => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::RED, self::_("Engineer")),
    12 => new CardiaCardInfo(3, PowerType::ONGOING, Faction::BLUE, self::_("Counselor")),
    13 => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::GREEN, self::_("Witch King")),
    14 => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::YELLOW, self::_("Elemental")),
    15 => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::RED, self::_("Mechanical Djinn")),
    16 => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::BLUE, self::_("Successor")),
  ],

];
