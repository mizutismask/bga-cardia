<?php

namespace Bga\Games\Cardia;

use Bga\Games\Cardia\objects\CardiaCard;
use Bga\Games\Cardia\objects\Faction;
use Bga\Games\Cardia\objects\InteractionType;
use Bga\Games\Cardia\objects\PowerType;

/**
 * @property CardManager cardManager
 * @property GameState gamestate
 * @property Globals globals
 */
trait ArgsTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state arguments
    ////////////

    /*function argChooseDuelCard() {
        $playerId = intval($this->getActivePlayerId());

        $destinations = $this->getPickedDestinationCards($playerId);

        return [
            'minimum' => 3,
            '_private' => [          // Using "_private" keyword, all data inside this array will be made private
                'active' => [       // Using "active" keyword inside "_private", you select active player(s)
                    'destinations' => $destinations,   // will be send only to active player(s)
                ]
            ],
        ];
    }*/


    function argChooseAction() {
        $playerId = intval($this->getActivePlayerId());

        $canPass = true;
        return [
            'canPass' => $canPass,
            'canResetTurn' => $this->globals->get(CAN_RESET_TURN),
        ];
    }

    function argCounters() {
        $players = $this->loadPlayersBasicInfos();
        $counters = array();
        foreach ($players as $playerId => $player) {

            $playerOrder = intval($player['player_no']);
            $name = "hand-cards-counter-$playerId";
            $counters[$name] = array('counter_name' => $name, 'counter_value' =>  $this->cardManager->countCardsOfTypeArgFromLocation(TABLE_CARD, $playerOrder, MATERIAL_LOCATION_HAND));

            $name = "discard-cards-counter-$playerId";
            $counters[$name] = array('counter_name' => $name, 'counter_value' => $this->cardManager->countCardsOfTypeArgFromLocation(TABLE_CARD, $playerOrder, MATERIAL_LOCATION_DISCARD));

            $name = "deck-cards-counter-$playerId";
            $counters[$name] = array('counter_name' => $name, 'counter_value' => $this->cardManager->countCardsOfTypeArgFromLocation(TABLE_CARD, $playerOrder, MATERIAL_LOCATION_DECK));

            $name = "signets-counter-$playerId";
            $counters[$name] = array('counter_name' => $name, 'counter_value' => $this->tokenManager->getSignetCount($playerOrder));
        }
        //$this->dump('*******************counters', $counters);
        return $counters;
    }

    function argInteractiveAbility() {
        $ability = $this->cardManager->getCard($this->globals->get(GLB_ABILITY_TO_RESOLVE));
        $prompt = $this->getPrompt($ability);
        $args = [
            'abilityCard' => $ability,
            'interactionType' => $this->getInteractionType($ability),
            "prompt" => $prompt["prompt"],
            ...$prompt["args"],
        ];
        if (in_array($args["interactionType"], [InteractionType::selectCardFromHand, InteractionType::selectCardFromDuels])) {
            $args["selectableCards"] = $this->getSelectableCards($ability);
            $args["optionalSelection"] = $this->isCardSelectionOptional($ability);
        }
        return  $args;
    }

    function argInteractiveAbilityStep2() {
        $ability = $this->cardManager->getCard($this->globals->get(GLB_ABILITY_TO_RESOLVE));
        $prompt = $this->getPrompt($ability);
        $args = [
            'abilityCard' => $ability,
            'interactionType' => $this->getInteractionTypeStep2($ability),
            "prompt" => $prompt["prompt"],
            ...$prompt["args"],
        ];
        if (in_array($args["interactionType"], [InteractionType::selectCardFromHand, InteractionType::selectCardFromDuels])) {
            $args["selectableCards"] = $this->getSelectableCards($ability);
            $args["optionalSelection"] = $this->isCardSelectionOptional($ability);
        }
        return $args;
    }

    function getSelectableCards(CardiaCard $ability, ?int $playerId = null) {
        $selectableCards = [];
        $playerId = $playerId ?? $this->getMostlyActivePlayerId();
        $playerPosition = $this->getPlayerPosition($playerId);
        if ($ability->type == PALACE_GUARD) {
            $faction = Faction::tryFrom($this->globals->get(GLB_SELECTED_FACTION));
            $selectableCards = $this->cardManager->getFactionCardsInHand($playerId, $faction);
        } else if ($ability->type == INVENTOR) {
            $selectableCards = $this->cardManager->getCardsInLocation(MATERIAL_LOCATION_ENCOUNTER);
        } else if ($ability->type == SWAMP_GUARDIAN) {
            $selectableCards = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $playerPosition, MATERIAL_LOCATION_HAND);
        } else if ($ability->type == MAGISTRA) {
            $selectableCards = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $playerPosition, MATERIAL_LOCATION_ENCOUNTER);
            //filter to keep only instant power type and value >= this card’s value 
            $abilityValue = $this->getCardValue($ability, true);
            $selectableCards = array_filter($selectableCards, function ($card) use ($abilityValue, $ability) {
                return $card->powerType == PowerType::IMMEDIATE && $card->id != $ability->id && $this->getCardValue($card, true) >= $abilityValue;
            });
        } else if ($ability->type == PRODIGY) {
            $selectableCards = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $playerPosition, MATERIAL_LOCATION_ENCOUNTER);
            $selectableCards = array_filter($selectableCards, function ($card) {
                return $this->getCardValue($card, true) <= 8;
            });
        }
        //$this->dump('*******************argSelectableCards', $selectableCards);
        return $selectableCards;
    }

    function getPrompt(CardiaCard $ability) {
        $prompt = "";
        switch ($ability->type) {
            case PALACE_GUARD:
                $faction = $this->globals->get(GLB_SELECTED_FACTION);
                $prompt = clienttranslate('${ability} ability: you may discard a ${faction} card to prevent +7 influence on your opponent’s card');
                return ["prompt" => $prompt, "args" => ["faction" => $faction, "ability" => $ability->name, 'i18n' => ['faction', 'ability']]];
            case AMBUSHER:
                $faction = $this->globals->get(GLB_SELECTED_FACTION);
                $prompt = clienttranslate('${ability} ability: choose a faction your opponent will have to discard');
                return ["prompt" => $prompt, "args" => ["ability" => $ability->name, 'i18n' => ['ability']]];
            case INVENTOR:
                $influence =  $this->globals->get(GLB_INVENTOR_PLUS_CARD) ? -3 : 3;
                return ["prompt" =>  clienttranslate('${ability} ability: choose a card to set ${influence} influence on it'), "args" => ["ability" => $ability->name, "influence" => $influence, 'i18n' => ['ability']]];
            case VOID_MAGE:
                return ["prompt" =>  clienttranslate('${ability} ability: choose a card to remove its modifiers or its ongoing tokens'), "args" => ["ability" => $ability->name, 'i18n' => ['ability']]];
            case SWAMP_GUARDIAN:
                return ["prompt" =>  clienttranslate('${ability} ability: choose a card to take it back in hand'), "args" => ["ability" => $ability->name, 'i18n' => ['ability']]];
            case MAGISTRA:
                return ["prompt" =>  clienttranslate('${ability} ability: choose a card to activate its ability'), "args" => ["ability" => $ability->name, 'i18n' => ['ability']]];
            case KINESIS_MAGE:
                $msg =  $this->globals->get(GLB_KINESIS_SOURCE_CARD) ? clienttranslate('${ability} ability: choose the destination card to put all the moved tokens and modifiers on') : clienttranslate('${ability} ability: choose the source card to move all tokens and modifiers from');
                return ["prompt" =>  $msg, "args" => ["ability" => $ability->name, 'i18n' => ['ability']]];
            case PRODIGY:
                return ["prompt" =>  clienttranslate('${ability} ability: choose a card with 8 or less influence to add +3 influence to it'), "args" => ["ability" => $ability->name, 'i18n' => ['ability']]];

            default:
                $this->error('*******************No prompt for ', $ability->name);
                return ["prompt" =>  "Unknown ability", "args" => []];
        }
    }

    function getInteractionType(CardiaCard $card): InteractionType {
        if (in_array($card->type, [PALACE_GUARD, AMBUSHER, BLACKMAILER, WITCH_KING])) {
            return InteractionType::selectFaction;
        }
        if (in_array($card->type, [SWAMP_GUARDIAN, REVOLUTIONARY, ELEMENTAL, SUCCESSOR])) {
            return InteractionType::selectCardFromHand;
        }
        if (in_array($card->type, [
            VOID_MAGE,
            MAGISTRA,
            INVENTOR,
            KINESIS_MAGE,
            ENVOY,
            PRODIGY,
            ILLUSIONIST,
            ELEMENTAL
        ])) {
            return InteractionType::selectCardFromDuels;
        }
        throw new \BgaVisibleSystemException("Unknown interaction type for card: " . $card->name);
    }
    function isCardSelectionOptional(CardiaCard $card) {
        return in_array($card->type, [PALACE_GUARD]);
    }

    function getInteractionTypeStep2(CardiaCard $card): InteractionType {
        if (in_array($card->type, [PALACE_GUARD])) {
            return InteractionType::selectCardFromHand;
        }
        if (in_array($card->type, [INVENTOR, KINESIS_MAGE])) {
            return InteractionType::selectCardFromDuels;
        }
        throw new \BgaVisibleSystemException("Unknown interaction type on step 2 for card: " . $card->name);
    }
}
