<?php

namespace Bga\Games\Cardia;

use \Bga\GameFramework\Actions\Types\IntArrayParam;
use \Bga\GameFramework\Actions\Types\StringParam;

use Bga\Games\Cardia\objects\CardiaCard;
use Bga\Games\Cardia\objects\Faction;
use Bga\Games\Cardia\objects\InteractionType;
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
    function actScrapyardChooseCard(int $version, int $cardId) {
        $this->checkVersion($version);
        $this->checkAction('actScrapyardChooseCard');
        $playerId = $this->getMostlyActivePlayerId();
        $card = $this->cardManager->getCard($cardId);
        $this->userAssertTrue($this->_("This card is not in your hand"), $card->location == "hand" && $card->location_arg == $playerId);

        $this->chooseScrapyardCard($this->getMostlyActivePlayerId(), $card);
        $this->gamestate->setPlayerNonMultiactive($playerId, $this->globals->get(GLB_NEXT_STATE_AFTER_SCRAPYARD));
    }

    function chooseScrapyardCard(int $playerId, CardiaCard $card) {
        $this->cardManager->moveCardToBottomOfDeck($card, $playerId);
    }

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

        //notify the move to the player as if it was really done, so that he can see his card
        $duelCount = $this->globals->get(GLB_DUEL_COUNT) + 1;
        $notifArgs = [
            'playerId' => $playerId,
            'type' => MATERIAL_TYPE_CARD,
            'from' => MATERIAL_LOCATION_HAND,
            'to' => MATERIAL_LOCATION_ENCOUNTER,
            'toArg' => $duelCount,
            'material' => [$this->cardManager->getCard($card->id)],
            'cardName' => $card->name,
            'i18n' => ['cardName'],
        ];
        $this->notifyPlayer($playerId, "materialMove",  "", $notifArgs);
        $this->notifyCounterChange();

        if ($this->gamestate->state()["name"] == "chooseFortuneTellerCard") {
            $this->notifyPlayer($this->getOpponentId($playerId), "materialMove",  "", $notifArgs);
            $this->gamestate->nextState('opponentChooseCard');
        } else {
            $this->gamestate->setPlayerNonMultiactive($playerId, '');
        }
    }

    function checkSelectionIsCorrect(?Faction $faction, ?array $cardIds, bool $skipFactionCheck = false) {
        $interactiveAbility = $this->cardManager->getCard($this->globals->get(GLB_ABILITY_TO_RESOLVE));
        $interactionType = $this->getInteractionType($interactiveAbility);
        if ($interactionType == InteractionType::selectFaction && !$skipFactionCheck) {
            $this->userAssertTrue(_("You have to select a faction"), $interactiveAbility &&  $faction);
            $this->globals->set(GLB_SELECTED_FACTION, $faction->value);
        } else if (in_array($interactionType, [InteractionType::selectCardFromHand, InteractionType::selectCardFromDuels])) {
            $optional = $this->isCardSelectionOptional($interactiveAbility);
            if (!$optional) {
                $this->userAssertTrue(_("You have to select a card"), !empty($cardIds));
                $qty = $this->getCardSelectionQuantity($interactiveAbility);
                $this->userAssertTrue(_("You did not select the expected number of cards"), count($cardIds) == $qty);
            }
        }
    }

    function actInteractiveAbility(int $version, #[StringParam(enum: ['G', 'R', 'Y', 'B'])] $faction, #[IntArrayParam()] ?array $cardIds, ?string $option) {
        $this->checkVersion($version);
        $this->checkAction('actInteractiveAbility');
        $playerId = $this->getMostlyActivePlayerId();
        $interactiveAbility = $this->cardManager->getCard($this->globals->get(GLB_ABILITY_TO_RESOLVE));
        $cards = [];
        $this->checkSelectionIsCorrect(Faction::tryFrom($faction), $cardIds);
        if ($cardIds) {
            foreach ($cardIds as $cardId) {
                $card = $this->cardManager->getCard($cardId);
                $this->userAssertTrue(_("this card does not exist"), $card);
                $cards[] = $card;
                $this->dump('*******************actInteractiveAbility on ', $card->name);
            }
        }
        $this->globals->set(GLB_SELECTED_CARD_ID, $cardIds);

        if ($cards) {
            $card = reset($cards);
            switch ($interactiveAbility->type) {
                case INVENTOR:
                    $this->userAssertTrue(_("This card is not part of an encounter"), $card->location == MATERIAL_LOCATION_ENCOUNTER);
                    break;
                case MAGISTRA:
                    $selectableCards = $this->getSelectableCards($interactiveAbility);
                    $this->userAssertTrue(_("The copied card must be a immediate power and have more or as much influence as your Magistra"),  $this->array_contains_card($selectableCards, $cardId));
                    break;
                case PRODIGY:
                    $selectableCards = $this->getSelectableCards($interactiveAbility);
                    $this->userAssertTrue(_("You have to select one of your cards with at most 8 influence"),  $this->array_contains_card($selectableCards, $cardId));
                    break;
                case SWAMP_GUARDIAN:
                    $selectableCards = $this->getSelectableCards($interactiveAbility);
                    $this->userAssertTrue(_("You have to select one of your played cards (but not Swamp Guardian)"),  $this->array_contains_card($selectableCards, $cardId));
                    break;
            }
        }

        $this->applyInteractiveAbility($interactiveAbility, Faction::tryFrom($faction), $cards, $option);
    }

    function actInteractiveAbilityStep2(int $version, #[IntArrayParam()] ?array $cardIds) {
        $this->checkVersion($version);
        $this->checkAction('actInteractiveAbilityStep2');
        $interactiveAbility = $this->cardManager->getCard($this->globals->get(GLB_ABILITY_TO_RESOLVE));

        $cards = [];
        $card = null;
        $this->checkSelectionIsCorrect(null, $cardIds, $interactiveAbility->type == PALACE_GUARD);
        if ($cardIds) {
            foreach ($cardIds as $cardId) {
                $card = $this->cardManager->getCard($cardId);
                $this->userAssertTrue(_("this card does not exist"), $card);
                $cards[] = $card;
                $this->dump('*******************actInteractiveAbilityStep2 on ', $card->name);
            }
        }
        $this->globals->set(GLB_SELECTED_CARD_ID, $cardIds);

        if ($cards) {
            $card = reset($cards);
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
        }

        $this->applyInteractiveAbilityStep2($interactiveAbility, $card);
    }

    function actChooseModifier(int $version, int $modifierValue) {
        $this->checkVersion($version);
        $this->checkAction('actChooseModifier');
        $this->userAssertTrue(_("Modifier should be -2 or +2"), $modifierValue == 2 || $modifierValue == -2);

        $this->chooseLibrarianModifier($modifierValue);
    }

    function chooseLibrarianModifier(int $modifierValue) {
        $playerId = $this->getMostlyActivePlayerId();
        $duels = $this->cardManager->getDuelsList();
        $card = $duels[count($duels)][$playerId];
        $this->cardManager->incCardModifier($card, $modifierValue);
        $this->globals->delete(GLB_NEXT_CARD_MODIFIER_AFTER_REVEAL . $playerId);
        $this->gamestate->nextState("evaluateDuel");
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
        $this->gamestate->setPlayerNonMultiactive($this->getMostlyActivePlayerId(), 'nextRound');
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
