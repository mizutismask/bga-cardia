<?php

namespace Bga\Games\Cardia;

use \Bga\GameFramework\Actions\Types\IntArrayParam;
use \Bga\GameFramework\Actions\Types\StringParam;

use Bga\Games\Cardia\objects\CardiaCard;
use Bga\Games\Cardia\objects\Faction;
use GameState;
use Globals;

/**
 * @property CardManager cardManager
 * @property GameState gamestate
 * @property Globals globals
 */
trait ActionTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Player actions
    //////////// 
    function actChooseDuelCard(int $version, int $cardId) {
        $this->checkVersion($version);
        $this->checkAction('actChooseDuelCard');
        $playerId = $this->getMostlyActivePlayerId();
        $card = $this->cardManager->getCard($cardId);
        $this->userAssertTrue($this->_("This card is not in your hand"), $card->location == "hand" && $card->location_arg == $playerId);

        $this->chooseDuelCard($this->getMostlyActivePlayerId(), $card);
    }

    function chooseDuelCard(int $playerId, CardiaCard $card) {
        $this->globals->set(GLB_LAST_CHOSEN_CARD . "_" . $playerId, json_encode($card));
        $this->gamestate->setPlayerNonMultiactive($playerId, '');
    }

    function actInteractiveAbility(int $version, #[StringParam(enum: ['G', 'R', 'Y', 'B'])] $faction, ?int $cardId, ?string $option) {
        $this->checkVersion($version);
        $this->checkAction('actInteractiveAbility');
        $playerId = $this->getMostlyActivePlayerId();
        $interactiveAbility = $this->cardManager->getCard($this->globals->get(GLB_ABILITY_TO_RESOLVE));
        $interactionType = $this->getInteractionType($interactiveAbility);
        $card = null;
        if ($interactionType == "selectFaction") {
            $this->userAssertTrue(_("You have to select a faction"), $interactiveAbility &&  $faction);
            $this->globals->set(GLB_SELECTED_FACTION, $faction);
        } else if ($interactionType == "selectCard") {
            $card = $this->cardManager->getCard($cardId);
            $this->userAssertTrue(_("You have to select a card"), $cardId && $card);
            $this->globals->set(GLB_SELECTED_CARD_ID, $cardId);
        }

        if ($card) {
            switch ($interactiveAbility->type) {
                case INVENTOR:
                    $this->userAssertTrue(_("This card is not part of an encounter"), $card->location == MATERIAL_LOCATION_ENCOUNTER);
                    break;
            }
        }

        $this->applyInteractiveAbility($interactiveAbility, Faction::tryFrom($faction), $card, $option);
    }

    function actInteractiveAbilityStep2(int $version, ?int $cardId) {
        $this->checkVersion($version);
        $this->checkAction('actInteractiveAbilityStep2');
        $playerId = $this->getMostlyActivePlayerId();
        $interactiveAbility = $this->cardManager->getCard($this->globals->get(GLB_ABILITY_TO_RESOLVE));
        $interactionType = $this->getInteractionTypeStep2($interactiveAbility);
        $card = null;
        $optional = in_array($interactiveAbility->type, [PALACE_GUARD]);
        if ($interactionType == "selectCard") {
            $card = $this->cardManager->getCard($cardId, $optional);
            if (!$optional) {
                $this->userAssertTrue(_("You have to select a card"), $cardId && $card);
            }
            if ($card) {
                $selectableCards = $this->argInteractiveAbilityStep2()["selectableCards"];
                switch ($interactiveAbility->type) {
                    case PALACE_GUARD:
                        $this->userAssertTrue(_("This card is not of the expected faction"), $this->array_contains_card($selectableCards, $cardId));
                        break;
                    case INVENTOR:
                        $this->userAssertTrue(_("This card is not part of an encounter"), $card->location == MATERIAL_LOCATION_ENCOUNTER);
                        $this->userAssertTrue(_("You’ve already modified this card, choose another one"), $card->id != $this->globals->get(GLB_INVENTOR_PLUS_CARD));
                        break;
                }
            }
            $this->globals->set(GLB_SELECTED_CARD_ID, $cardId);
        }

        $this->applyInteractiveAbilityStep2($interactiveAbility, $card);
    }

    /**
     * @throws BgaUserException
     */
    function actPass(int $version) {
        $this->checkVersion($version);
        $this->checkAction('actPass');

        $args = $this->argChooseAction();

        if (!$args['canPass']) {
            throw new \BgaUserException($this->_("You cannot pass"));
        }

        //$this->dbInsertContextLog(ACTION_PLAY_TICKET, $festivalId, $slotId);
        //$this->changeNextStateFromContext();

        $this->gamestate->nextState('nextPlayer');
    }

    /** Undo all the player turn actions. 
     * @throws BgaUserException
     */
    function actResetPlayerTurn(int $version) {
        $this->checkVersion($version);
        $possible = $this->globals->get(CAN_RESET_TURN);
        if (!$possible) {
            throw new \BgaUserException(self::_("Undo is not available"));
        }
        $this->undoRestorePoint();
        $this->toggleResetTurn(false);
        $this->gamestate->reloadState();
    }
}
