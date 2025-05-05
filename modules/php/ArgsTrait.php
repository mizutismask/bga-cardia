<?php

namespace Bga\Games\Cardia;

use Bga\Games\Cardia\objects\CardiaCard;
use Bga\Games\Cardia\objects\Faction;
use Bga\Games\Cardia\objects\InteractionType;
use Bga\Games\Cardia\objects\PowerType;

/**
 * @property CardManager cardManager
 * @property TokenManager tokenManager
 * @property GameState gamestate
 * @property Globals globals
 */
trait ArgsTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state arguments
    ////////////

    function argChooseDuelCard() {
        $private = [];
        foreach ($this->getPlayersIds() as $playerId) {
            $private[$playerId] = [];
            $private[$playerId]["blackmailerFaction"] = $this->globals->get(GLB_BLACKMAILER_FACTION . $playerId);
        }

        return [
            '_private' => $private,
        ];
    }


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

    function getAbilityToResolve(): CardiaCard|null {
        $abilityId = $this->globals->get(GLB_ABILITY_TO_RESOLVE);
        $ability = $abilityId ? $this->cardManager->getCard($abilityId, true) : null;
        $copiedType = $this->globals->get(GLB_ABILITY_TO_RESOLVE_COPIED_TYPE);
        if ($ability && $copiedType) {
            $this->copyAbility($ability, $copiedType);
        }
        return $ability;
    }

    function getOriginalAbilityToResolveType(): int {
        $abilityId = $this->globals->get(GLB_ABILITY_TO_RESOLVE);
        $ability = $abilityId ? $this->cardManager->getCard($abilityId, true) : null;
        return $ability ? $ability->type : -1;
    }

    function argInteractiveAbility() {
        $ability = $this->getAbilityToResolve();
        $promptArgs = $this->getPromptArgs($ability);
        $args = [
            'abilityCard' => $ability,
            'interactionType' => $this->getInteractionType($ability),
            ...$promptArgs,
        ];
        if (in_array($args["interactionType"], [InteractionType::selectCardFromHand, InteractionType::selectCardFromDuels])) {
            $args["selectableCards"] = $this->getSelectableCards($ability);
            $optional = $this->isCardSelectionOptional($ability);
            $args["optionalSelection"] = $optional;
            if (!$optional) {
                $args["qty"] = $this->getCardSelectionQuantity($ability);
            }
        }
        return  $args;
    }

    function argInteractiveAbilityStep2() {
        $ability = $this->getAbilityToResolve();
        $promptArgs = $this->getPromptArgs($ability);
        $args = [
            'abilityCard' => $ability,
            'interactionType' => $this->getInteractionTypeStep2($ability),
            ...$promptArgs,
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
            $selectableCards = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $playerPosition, MATERIAL_LOCATION_ENCOUNTER);
            $selectableCards = array_values(array_filter($selectableCards, function ($card) use ($ability) {
                return  $card->id != $ability->id;
            }));
        } else if ($ability->type == MAGISTRA) {
            $selectableCards = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $playerPosition, MATERIAL_LOCATION_ENCOUNTER);
            //filter to keep only instant power type and value >= this card’s value 
            $abilityValue = $this->getCardValue($ability, true);
            $selectableCards = array_values(array_filter($selectableCards, function ($card) use ($abilityValue, $ability) {
                return $card->powerType == PowerType::IMMEDIATE && $card->id != $ability->id && $this->getCardValue($card, true) >= $abilityValue;
            }));
        } else if ($ability->type == PRODIGY) {
            $selectableCards = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $playerPosition, MATERIAL_LOCATION_ENCOUNTER);
            $selectableCards = array_values(array_filter($selectableCards, function ($card) {
                return $this->getCardValue($card, true) <= 8;
            }));
        } else if ($ability->type == ILLUSIONIST) {
            $selectableCards = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $playerPosition, MATERIAL_LOCATION_ENCOUNTER);
            $selectableCards = array_values(array_filter($selectableCards, function ($card) use ($ability) {
                return (!$this->tokenManager->hasSignet($card->id)) && $card->id != $ability->id;
            }));
        } else if ($ability->type == ELEMENTAL) {
            $selectableCards = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $playerPosition, MATERIAL_LOCATION_HAND);
            $selectableCards = array_values(array_filter($selectableCards, function ($card) {
                return $card->powerType == PowerType::IMMEDIATE;
            }));
        } else if ($ability->type == REVOLUTIONARY) {
            $selectableCards = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $playerPosition, MATERIAL_LOCATION_HAND);
        } else if ($ability->type == VOID_MAGE) {
            $selectableCards = $this->cardManager->getCardsInLocation(MATERIAL_LOCATION_ENCOUNTER);
        }
        //$this->dump('*******************argSelectableCards', $selectableCards);
        return $selectableCards;
    }
    function getPromptArgs(CardiaCard $ability) {
        $defaultArgs = ["ability" => $ability->name, "ability" => $ability->name, 'i18n' => ['ability', "faction"]];
        switch ($ability->type) {
            case PALACE_GUARD:
                $faction = Faction::tryFrom($this->globals->get(GLB_SELECTED_FACTION));
                return array_merge($defaultArgs, ["faction" => $faction ? $this->getColorName($faction) : null]);

            case INVENTOR:
                $influence =  $this->globals->get(GLB_INVENTOR_PLUS_CARD) ? -3 : 3;
                return array_merge($defaultArgs, ["influence" => $influence]);

            default:
                return $defaultArgs;
        }
    }

    function getInteractionType(CardiaCard $card): InteractionType {
        if (in_array($card->type, [PALACE_GUARD, AMBUSHER, BLACKMAILER, WITCH_KING])) {
            return InteractionType::selectFaction;
        }
        if (in_array($card->type, [REVOLUTIONARY, ELEMENTAL, SUCCESSOR])) {
            return InteractionType::selectCardFromHand;
        }
        if (in_array($card->type, [
            SWAMP_GUARDIAN,
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
        return in_array($card->type, [PALACE_GUARD, ENVOY]);
    }

    function getCardSelectionQuantity(CardiaCard $card) {
        if (in_array($card->type, [REVOLUTIONARY, SUCCESSOR])) {
            return 2;
        }
        return 1;
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
