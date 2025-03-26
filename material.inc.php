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
  101 => new CardiaCardInfo(1, PowerType::IMMEDIATE, Faction::GREEN, self::_("Hired Blade")),
  102 => new CardiaCardInfo(2, PowerType::IMMEDIATE, Faction::YELLOW, self::_("Void Mage")),
  103 => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::RED, self::_("Surgeon")),
  104 => new CardiaCardInfo(4, PowerType::ONGOING, Faction::BLUE, self::_("Mediator")),
  105 => new CardiaCardInfo(5, PowerType::IMMEDIATE, Faction::GREEN, self::_("Saboteur")),
  106 => new CardiaCardInfo(6, PowerType::IMMEDIATE, Faction::YELLOW, self::_("Fortune Teller")),
  107 => new CardiaCardInfo(7, PowerType::IMMEDIATE, Faction::RED, self::_("Palace Guard")),
  108 => new CardiaCardInfo(8, PowerType::ONGOING, Faction::BLUE, self::_("Judge")),
  109 => new CardiaCardInfo(9, PowerType::IMMEDIATE, Faction::GREEN, self::_("Ambusher")),
  110 => new CardiaCardInfo(10, PowerType::IMMEDIATE, Faction::YELLOW, self::_("Puppeteer")),
  111 => new CardiaCardInfo(11, PowerType::IMMEDIATE, Faction::RED, self::_("Clockmaker")),
  112 => new CardiaCardInfo(12, PowerType::ONGOING, Faction::BLUE, self::_("Treasurer")),
  113 => new CardiaCardInfo(13, PowerType::IMMEDIATE, Faction::GREEN, self::_("Swamp Guardian")),
  114 => new CardiaCardInfo(14, PowerType::IMMEDIATE, Faction::YELLOW, self::_("Magistra")),
  115 => new CardiaCardInfo(15, PowerType::IMMEDIATE, Faction::RED, self::_("Inventor")),
  116 => new CardiaCardInfo(16, PowerType::IMMEDIATE, Faction::BLUE, self::_("DJinn")),

  // deck 2
  201 => new CardiaCardInfo(1, PowerType::IMMEDIATE, Faction::GREEN, self::_("Poisoner")),
  202 => new CardiaCardInfo(2, PowerType::IMMEDIATE, Faction::YELLOW, self::_("Kinesis Mage")),
  203 => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::RED, self::_("Envoy")),
  204 => new CardiaCardInfo(4, PowerType::ONGOING, Faction::BLUE, self::_("Tax Collector")),
  205 => new CardiaCardInfo(5, PowerType::IMMEDIATE, Faction::GREEN, self::_("Revolutionary")),
  206 => new CardiaCardInfo(6, PowerType::IMMEDIATE, Faction::YELLOW, self::_("Librarian")),
  207 => new CardiaCardInfo(7, PowerType::IMMEDIATE, Faction::RED, self::_("Prodigy")),
  208 => new CardiaCardInfo(8, PowerType::ONGOING, Faction::BLUE, self::_("Aristocrat")),
  209 => new CardiaCardInfo(9, PowerType::IMMEDIATE, Faction::GREEN, self::_("Blackmailer")),
  210 => new CardiaCardInfo(10, PowerType::IMMEDIATE, Faction::YELLOW, self::_("Illusionist")),
  211 => new CardiaCardInfo(11, PowerType::IMMEDIATE, Faction::RED, self::_("Engineer")),
  212 => new CardiaCardInfo(12, PowerType::ONGOING, Faction::BLUE, self::_("Counselor")),
  213 => new CardiaCardInfo(13, PowerType::IMMEDIATE, Faction::GREEN, self::_("Witch King")),
  214 => new CardiaCardInfo(14, PowerType::IMMEDIATE, Faction::YELLOW, self::_("Elemental")),
  215 => new CardiaCardInfo(15, PowerType::IMMEDIATE, Faction::RED, self::_("Mechanical Djinn")),
  216 => new CardiaCardInfo(16, PowerType::IMMEDIATE, Faction::BLUE, self::_("Successor")),
];
