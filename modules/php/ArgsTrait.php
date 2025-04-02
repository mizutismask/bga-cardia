<?php

namespace Bga\Games\Cardia;

use Bga\Games\Cardia\objects\CardiaCard;
use Bga\Games\Cardia\objects\Faction;

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
        return [
            'abilityCard' => $ability,
            'interactionType' => $this->getInteractionType($ability),
            "prompt" => $prompt["prompt"],
            ...$prompt["args"],
        ];
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
        if ($args["interactionType"] == "selectCard") {
            $args["selectableCards"] = $this->getSelectableCards($ability);
            $args["optionalSelection"] = $this->isCardSelectionOptional($ability);
        }
        return $args;
    }

    function getSelectableCards(CardiaCard $ability) {
        $selectableCards = [];
        $playerId = $this->getMostlyActivePlayerId();
        if ($ability->type == PALACE_GUARD) {
            $faction = Faction::tryFrom($this->globals->get(GLB_SELECTED_FACTION));
            $selectableCards = $this->cardManager->getFactionCardsInHand($playerId, $faction);
        } else if ($ability->type == INVENTOR) {
            $selectableCards = $this->cardManager->getCardsInLocation(MATERIAL_LOCATION_ENCOUNTER);
        }
        return $selectableCards;
    }

    function getPrompt(CardiaCard $ability) {
        $prompt = "";
        switch ($ability->type) {
            case PALACE_GUARD:
                $faction = $this->globals->get(GLB_SELECTED_FACTION);
                $prompt = clienttranslate('${ability} ability: you may discard a ${faction} card to prevent +7 influence on your opponent’s card');
                return ["prompt" => $prompt, "args" => ["faction" => $faction, "ability" => $ability->name, 'i18n' => ['faction', 'ability']]];
            case INVENTOR:
                $influence =  $this->globals->get(GLB_INVENTOR_PLUS_CARD) ? -3 : 3;
                return ["prompt" =>  clienttranslate('${ability} ability: choose a card to set ${influence} influence on it'), "args" => ["ability" => $ability->name, "influence" => $influence, 'i18n' => ['ability']]];
        }
    }

    function getInteractionType(CardiaCard $card) {
        if (in_array($card->type, [PALACE_GUARD, AMBUSHER])) {
            return 'selectFaction';
        }
        if (in_array($card->type, [VOID_MAGE, SWAMP_GUARDIAN, MAGISTRA, INVENTOR])) {
            return 'selectCard';
        }
        throw new \BgaVisibleSystemException("Unknown interaction type for card: " . $card->name);
    }
    function isCardSelectionOptional(CardiaCard $card) {
        return in_array($card->type, [PALACE_GUARD]);
    }

    function getInteractionTypeStep2(CardiaCard $card) {
        if (in_array($card->type, [PALACE_GUARD, INVENTOR])) {
            return 'selectCard';
        }
        throw new \BgaVisibleSystemException("Unknown interaction type on step 2 for card: " . $card->name);
    }
}
