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
  101 => new CardiaCardInfo(1, PowerType::IMMEDIATE, Faction::GREEN, clienttranslate("Hired Blade")),
  102 => new CardiaCardInfo(2, PowerType::IMMEDIATE, Faction::YELLOW, clienttranslate("Void Mage")),
  103 => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::RED, clienttranslate("Surgeon")),
  104 => new CardiaCardInfo(4, PowerType::ONGOING, Faction::BLUE, clienttranslate("Mediator")),
  105 => new CardiaCardInfo(5, PowerType::IMMEDIATE, Faction::GREEN, clienttranslate("Saboteur")),
  106 => new CardiaCardInfo(6, PowerType::IMMEDIATE, Faction::YELLOW, clienttranslate("Fortune Teller")),
  107 => new CardiaCardInfo(7, PowerType::IMMEDIATE, Faction::RED, clienttranslate("Palace Guard")),
  108 => new CardiaCardInfo(8, PowerType::ONGOING, Faction::BLUE, clienttranslate("Judge")),
  109 => new CardiaCardInfo(9, PowerType::IMMEDIATE, Faction::GREEN, clienttranslate("Ambusher")),
  110 => new CardiaCardInfo(10, PowerType::IMMEDIATE, Faction::YELLOW, clienttranslate("Puppeteer")),
  111 => new CardiaCardInfo(11, PowerType::IMMEDIATE, Faction::RED, clienttranslate("Clockmaker")),
  112 => new CardiaCardInfo(12, PowerType::ONGOING, Faction::BLUE, clienttranslate("Treasurer")),
  113 => new CardiaCardInfo(13, PowerType::IMMEDIATE, Faction::GREEN, clienttranslate("Swamp Guardian")),
  114 => new CardiaCardInfo(14, PowerType::IMMEDIATE, Faction::YELLOW, clienttranslate("Magistra")),
  115 => new CardiaCardInfo(15, PowerType::IMMEDIATE, Faction::RED, clienttranslate("Inventor")),
  116 => new CardiaCardInfo(16, PowerType::IMMEDIATE, Faction::BLUE, clienttranslate("DJinn")),

  // deck 2
  201 => new CardiaCardInfo(1, PowerType::IMMEDIATE, Faction::GREEN, clienttranslate("Poisoner")),
  202 => new CardiaCardInfo(2, PowerType::IMMEDIATE, Faction::YELLOW, clienttranslate("Kinesis Mage")),
  203 => new CardiaCardInfo(3, PowerType::IMMEDIATE, Faction::RED, clienttranslate("Envoy")),
  204 => new CardiaCardInfo(4, PowerType::IMMEDIATE, Faction::BLUE, clienttranslate("Tax Collector")),
  205 => new CardiaCardInfo(5, PowerType::IMMEDIATE, Faction::GREEN, clienttranslate("Revolutionary")),
  206 => new CardiaCardInfo(6, PowerType::IMMEDIATE, Faction::YELLOW, clienttranslate("Librarian")),
  207 => new CardiaCardInfo(7, PowerType::IMMEDIATE, Faction::RED, clienttranslate("Prodigy")),
  208 => new CardiaCardInfo(8, PowerType::ONGOING, Faction::BLUE, clienttranslate("Aristocrat")),
  209 => new CardiaCardInfo(9, PowerType::IMMEDIATE, Faction::GREEN, clienttranslate("Blackmailer")),
  210 => new CardiaCardInfo(10, PowerType::IMMEDIATE, Faction::YELLOW, clienttranslate("Illusionist")),
  211 => new CardiaCardInfo(11, PowerType::IMMEDIATE, Faction::RED, clienttranslate("Engineer")),
  212 => new CardiaCardInfo(12, PowerType::ONGOING, Faction::BLUE, clienttranslate("Counselor")),
  213 => new CardiaCardInfo(13, PowerType::IMMEDIATE, Faction::GREEN, clienttranslate("Witch King")),
  214 => new CardiaCardInfo(14, PowerType::IMMEDIATE, Faction::YELLOW, clienttranslate("Elemental")),
  215 => new CardiaCardInfo(15, PowerType::ONGOING, Faction::RED, clienttranslate("Mechanical Djinn")),
  216 => new CardiaCardInfo(16, PowerType::IMMEDIATE, Faction::BLUE, clienttranslate("Successor")),
];

$this->LOCATIONS = [
  SERPENT_TEMPLE => clienttranslate("Serpent Temple"),
  BAZAAR => clienttranslate("Bazaar"),
  FOUNDERS_DAY => clienttranslate("Founder’s day"),
  GRAND_LIBRARY => clienttranslate("Grand Library"),
  SCRAPYARD => clienttranslate("Scrapyard"),
  AUCTION_HOUSE => clienttranslate("Auction House"),
  HAUNTED_CATACOMBS => clienttranslate("Haunted Catacombs"),
  FOGGY_SWAMP => clienttranslate("Foggy Swamp"),  
];