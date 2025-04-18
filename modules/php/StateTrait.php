<?php

namespace Bga\Games\Cardia;

use Bga\GameFramework\Db\Globals;
use Bga\Games\Cardia\objects\CardiaCard;
use Bga\Games\Cardia\objects\Faction;
use Bga\Games\Cardia\objects\PowerType;
use Bga\Games\Cardia\objects\TokenType;
use BgaSystemException;
use BgaUserException;
use feException;
use GameState;
use Random\RandomException;

/**
 * @property CardManager cardManager
 * @property TokenManager $tokenManager
 * @property GameState gamestate
 * @property Globals globals
 */
trait StateTrait {

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state actions
    ////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.
    */

    function stDealInitialSetup() {
        $playersIds = $this->getPlayersIds();

        foreach ($playersIds as $playerId) {
            //$this->cardManager->pickInitialDestinationCards($playerId);
        }

        $this->gamestate->nextState('');
    }

    function stDuelReveal() {
        $players = $this->getPlayers();
        $duelCount = $this->globals->inc(GLB_DUEL_COUNT, 1);
        $stateTransition = 'finishDuel';
        $cards = [];
        $immediateLoosers = [];
        foreach ($players as $playerId => $player) {
            $card = $this->getCardiaCardFromDb(json_decode($this->globals->get(GLB_LAST_CHOSEN_CARD . "_" . $playerId), true));
            $cards[] = $this->cardManager->playCard($card, $playerId, $duelCount);

            $modifierToAdd = $this->globals->get(GLB_NEXT_CARD_MODIFIER . $playerId, 0);
            if ($modifierToAdd != 0) {
                $this->cardManager->incCardModifier($card, $modifierToAdd);
                $this->globals->delete(GLB_NEXT_CARD_MODIFIER . $playerId);
            }

            if ($this->getScenery() == AUCTION_HOUSE) {
                $revealedCardValue = $this->getCardValue($card, true);
                $duels = $this->cardManager->getDuelsList();
                if (isset($duels[$duelCount - 1])) {
                    $previousCard =  $duels[$duelCount - 1][$playerId];
                    if ($revealedCardValue < $this->getCardValue($previousCard, true)) {
                        $this->cardManager->discardTopOfDeck($playerId, $player["player_no"]);
                    }
                }
            }
            if ($this->getScenery() == HAUNTED_CATACOMBS) {
                $revealedCardValue = $card->faction;
                $duels = $this->cardManager->getDuelsList();
                if (isset($duels[$duelCount - 1])) {
                    $previousCard =  $duels[$duelCount - 1][$playerId];
                    if ($card->faction == $previousCard->faction) {
                        //immediatly loose the round
                        $immediateLoosers[] = $playerId;
                    }
                }
            }
        }

        if ($immediateLoosers) {
            if (count($immediateLoosers) == 1) {
                $this->stFinishDuel([$this->getOpponentId(reset($immediateLoosers))]);
            } else {
                $this->stFinishDuel([], true);
            }
        } else {
            $eval = $this->evaluateDuelValues($cards);

            if ($eval["hasWinner"]) {
                $this->globals->set(GLB_ABILITY_TO_RESOLVE, $eval["looser"]->id);
                $stateTransition = 'looserAbility';
            }

            if (!$eval["interrupt"]) {
                $this->dump('*******************stDuelReveal', $stateTransition);
                $this->gamestate->nextState($stateTransition);
            }
        }
    }

    function evaluateDuelValues(array $cards) {
        $hasWinner = false;
        $maxCard = null;
        $minCard = null;
        $interrupt = false;
        foreach ($cards as $card) {
            if ($minCard === null) {
                $minCard = $card;
                $maxCard = $card;
            }
            $value = $this->getCardValue($card);
            if ($value > $maxCard->modifiedValue) {
                $maxCard = $card;
            }
            if ($value < $minCard->modifiedValue) {
                $minCard = $card;
            }
        }

        if ($minCard->id != $maxCard->id) {
            $hasWinner = true;
            $winningPlayerId = $this->getPlayerIdFromPosition($maxCard->type_arg);

            $operator = ">";
            $this->notifyWithName('msg', clienttranslate('${cardName1} beats ${cardName2}: ${winnerValue} ${operator} ${looserValue}'), [
                'winnerValue' => $maxCard->modifiedValue,
                'looserValue' => $minCard->modifiedValue,
                'operator' => $operator,
                'cardName1' => $maxCard->name,
                'cardName2' => $minCard->name,
            ]);

            $this->tokenManager->addSignetOnCard($maxCard->id, $minCard->id);

            if ($maxCard->type == ARISTOCRAT && $this->isActiveCardInPlay(ARISTOCRAT, $winningPlayerId)) {
                $this->tokenManager->addSignetOnCard($maxCard->id, null, true); //todo reevaluate everything when ongoing removed
            }
            if ($this->isActiveCardInPlay(MECHANICAL_DJINN, $winningPlayerId)) {
                $djinn = $this->cardManager->getCardInPlay(MECHANICAL_DJINN, $winningPlayerId);
                //check if this card is immediately following the djinn
                if ($djinn && $djinn->location_arg == $maxCard->location_arg - 1) {
                    //win the game
                    $interrupt = true;
                    $this->stFinishDuel([$winningPlayerId]);
                }
            }
        } else {
            //tie->remove signets if any
            foreach ($cards as $card) {
                $this->tokenManager->discardTokenOfTypeOnCard($card, TokenType::SIGIL);
            }

            $this->notifyWithName('msg', clienttranslate('Tie on value: ${winnerValue}'), [
                'winnerValue' => $maxCard->modifiedValue,
            ]);

            //check if any or both players have played judge
            $players = $this->getPlayersIds();
            foreach ($players as $playerId) {
                $judge = $this->isActiveCardInPlay(JUDGE, $playerId);
                if ($judge) {
                    //$this->dump('*******************judge active for ', $playerId);
                    $myCard = $this->getFirstElementInArray(array_filter($cards, fn($c) => $c->type_arg == $this->getPlayerPosition($playerId)));
                    $this->tokenManager->addSignetOnCard($myCard->id, null);
                    $this->notifyWithName('msg', clienttranslate('${ability} ability: ${playerName} wins the encounter'), [
                        "ability" => $judge->name,
                        "playerName" => $this->getPlayerName($playerId),
                        'i18n' => ['ability']
                    ]);
                }
            }
        }


        if ($this->getScenery() == FOUNDERS_DAY) {
            $finalWinners = $this->getPlayersHavingSuccessiveWins(3);
            if (count($finalWinners) > 0) {
                $this->stFinishDuel($finalWinners);
                $interrupt = true;
            }
        }
        $result = ["hasWinner" => $hasWinner, "winner" => $maxCard, "looser" => $minCard, "interrupt" => $interrupt];

        return $result;
    }

    function getPlayersHavingSuccessiveWins(int $minimumWins) {
        $players = $this->loadPlayersBasicInfos();
        $withEnoughSuccessiveWins = [];
        foreach ($players as $playerId => $player) {
            $this->dump('*******************$playerId', $playerId);
            $cards = $this->cardManager->getCardsOfTypeArgFromLocationOrderBy(TABLE_CARD, $player["player_no"], MATERIAL_LOCATION_ENCOUNTER, "card_location_arg");
            $this->dump('*******************$cards', $cards);
            //check if player has 3 successive cards with signets looping through cards
            if (count($cards) < $minimumWins) {
                continue;
            }

            $successiveWins = 0;
            //a win is a card with signets
            for ($i = 0; $i < count($cards); $i++) {
                $card = $cards[$i];
                $signets = $this->tokenManager->getSignetsOnCard($card->id);
                if (count($signets) > 0) {
                    $successiveWins++;
                } else {
                    $successiveWins = 0;
                }
                if ($successiveWins == $minimumWins) {
                    $withEnoughSuccessiveWins[] = $playerId;
                    $this->dump('*******************adding', $playerId);
                    break;  // Add this break to stop checking more cards once we've found enough successive wins
                }
            }
        }
        $this->dump('*******************$withEnoughSuccessiveWins', $withEnoughSuccessiveWins);
        return $withEnoughSuccessiveWins;
    }

    function isActiveCardInPlay($cardType, $playerId) {
        $card = $this->cardManager->getCardInPlay($cardType, $playerId);

        if ($card && $card->powerType == PowerType::ONGOING) {
            //check is ongoing card is still active
            if (!$this->tokenManager->hasOngoingToken($card->id)) {
                $card = null;
            }
        }
        return $card;
    }

    function stLooserAbility() {
        $card = $this->cardManager->getCard($this->globals->get(GLB_ABILITY_TO_RESOLVE));
        $possible = $this->isAbilityPossible($card, $this->getPlayerIdFromPosition($card->type_arg), null);
        $this->dump('*******************isAbilityNeedingInteraction', $this->isAbilityNeedingInteraction($card));
        $this->dump('*******************isAbilityPossible', $possible);
        if ($this->isAbilityNeedingInteraction($card)) {

            if ($possible) {
                if ($card->type == REVOLUTIONARY || $card->type == SUCCESSOR) {
                    //opponent is acting
                    $this->globals->set(GLB_PLAYER_TO_ACTIVATE, $this->getOpponentId($this->getPlayerIdFromPosition($card->type_arg)));
                } else {
                    //card owner is acting
                    $this->globals->set(GLB_PLAYER_TO_ACTIVATE, $this->getPlayerIdFromPosition($card->type_arg));
                }
                $this->gamestate->nextState('interactiveAbility');
            } else {
                $this->notifyWithName('msg', clienttranslate('${cardName} ability impossible to resolve'), [
                    'cardName' => $card->name,
                ]);

                if ($card->type != DJINN) {
                    $this->gamestate->nextState('finishDuel');
                }
            }
        } else {
            $this->applyAbility($card, $this->cardManager->getDuelsList(), $this->tokenManager->getSignetsOnCards(), $this->globals->get(GLB_DUEL_COUNT));
            if ($card->type != DJINN) {
                $this->gamestate->nextState('finishDuel');
            }
        }
    }

    function stActivatePlayerForAbility() {
        $playerId = $this->globals->get(GLB_PLAYER_TO_ACTIVATE);
        $this->gamestate->changeActivePlayer($playerId);
        $this->gamestate->nextState($this->globals->get(GLB_STEP_2) ? 'interactiveAbilityStep2' : 'interactiveAbility');
    }

    function isAbilityNeedingInteraction(CardiaCard $card): bool {
        $abilitiesNeedingInteraction = [
            VOID_MAGE,
            PALACE_GUARD,
            AMBUSHER,
            SWAMP_GUARDIAN,
            MAGISTRA,
            INVENTOR,
            KINESIS_MAGE,
            ENVOY,
            REVOLUTIONARY,
            /*LIBRARIAN,*/
            PRODIGY,
            /* BLACKMAILER,
            ILLUSIONIST,*/
            WITCH_KING,
            /* ELEMENTAL,*/
            SUCCESSOR
        ];
        return in_array($card->type, $abilitiesNeedingInteraction);
    }

    /**
     * 
     * @param CardiaCard $ability 
     * @param array $duels 
     * @param CardiaToken[] $signets 
     * @param int duelNumber
     * @return void 
     * @throws BgaUserException 
     */
    function applyAbility(CardiaCard $ability, array $duels, array $signets, int $duelNumber) {
        $this->notifyWithName('msg', clienttranslate('${cardName} ability'), [
            'cardName' => $ability->name,
        ]);
        $this->dump('*******************applyAbility', $ability->name);

        if ($ability->powerType == PowerType::ONGOING) {
            $this->tokenManager->addOngoingTokenOnCard($ability->id);
        }

        $opponentTypeArg = $ability->type_arg == 1 ? 2 : 1;
        $opponentId = $this->getPlayerIdFromPosition($opponentTypeArg);
        $playerId = $this->getPlayerIdFromPosition($ability->type_arg);
        switch ($ability->type) {
            case HIRED_BLADE:
                $opposing = $this->cardManager->getOpposingCard($ability, $duels);
                $this->discardDuelCard($ability);
                $this->discardDuelCard($opposing);
                break;
            case MEDIATOR:
                $opposing = $this->cardManager->getOpposingCard($ability, $duels);
                $this->tokenManager->discardTokenOfTypeOnCard($opposing, TokenType::SIGIL);
                break;
            case SABOTEUR:
                for ($i = 0; $i < 2; $i++) {
                    $this->cardManager->discardTopOfDeck($opponentId, $opponentTypeArg);
                }
                break;
            case PUPPETEER:
                $opposing = $this->cardManager->getOpposingCard($ability, $duels);
                $opponentHand = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $opposing->type_arg, MATERIAL_LOCATION_HAND);
                $replacement = $this->getRandomValue($opponentHand);
                $this->discardDuelCard($opposing,  clienttranslate('${cardName} is replaced by ${cardName2}'), ["cardName" => $opposing->name, "cardName2" => $replacement->name]);
                $this->cardManager->moveCardToLocation($replacement, $opposing->location, $opposing->location_arg, true, $opponentId);
                $this->evaluateDuelValues([$ability, $replacement]);
                $this->cardManager->replenishHands();
                break;
            case TREASURER:
                if ($duelNumber > 1) {
                    $previousDuelCards = $duels[$duelNumber - 1];
                    $cardsWithSignet = array_filter($previousDuelCards, function ($c) use ($signets) {
                        return !empty(array_filter($signets, fn($s) => $s->location == MATERIAL_LOCATION_CARD && $s->location_arg == $c->id));
                    });

                    $winningCard = reset($cardsWithSignet);
                    if ($winningCard) {
                        $this->tokenManager->addSignetOnCard($winningCard->id, null, true);
                    }
                }
                break;
            case DJINN:
                $this->stFinishDuel([$playerId]);
                break;
            case SURGEON:
                $value = -5;
                $this->globals->set(GLB_NEXT_CARD_MODIFIER . $playerId, $value);
                $this->notifyWithName('nextCardModifier', "", [
                    'value' => $value,
                    'playerId' => $playerId,
                    'playerPosition' => $this->getPlayerPosition($playerId),
                ]);
                break;
            case CLOCKMAKER:
                $value = 3;
                $this->globals->set(GLB_NEXT_CARD_MODIFIER . $playerId, $value);
                $this->notifyWithName('nextCardModifier', "", [
                    'value' => $value,
                    'playerId' => $playerId,
                    'playerPosition' => $this->getPlayerPosition($playerId),
                ]);
                if ($duelNumber > 1) {
                    $previousCard = $duels[$duelNumber - 1][$playerId];
                    $this->cardManager->incCardModifier($previousCard, $value);
                    $this->evaluateDuelValues([$previousCard, $this->cardManager->getOpposingCard($previousCard, $this->cardManager->getDuelsList())]);
                }
                break;
            case JUDGE:
                $duels = $this->cardManager->getDuelsList();
                $tied = $this->getTiedDuels($duels);
                foreach ($tied as $duelNumber => $duel) {
                    $this->tokenManager->addSignetOnCard($duel[$playerId]->id, null);
                }
                break;
            case POISONER:
                $opposing = $this->cardManager->getOpposingCard($ability, $duels);
                $opposingValue = $this->getCardValue($opposing);
                $cardValue = $this->getCardValue($ability);
                //opposingValue - newOpposingModifier = cardValue
                $newOpposingModifier = $opposingValue - $cardValue;
                $this->cardManager->updateCardModifier($opposing,  $newOpposingModifier * -1);
                $this->evaluateDuelValues([$ability, $opposing]);
                break;
            case TAX_COLLECTOR:
                $this->cardManager->incCardModifier($ability,  4);
                $opposing = $this->cardManager->getOpposingCard($ability, $duels);
                $this->evaluateDuelValues([$ability, $opposing]);
                break;
            case ENGINEER:
                $value = 5;
                $this->globals->set(GLB_NEXT_CARD_MODIFIER_AFTER_ABILITY_TRIGGERED . $playerId, $value);
                $this->notifyWithName('nextCardModifier', "", [
                    'value' => $value,
                    'playerId' => $playerId,
                    'playerPosition' => $this->getPlayerPosition($playerId),
                    'disabled' => true,
                ]);
                break;
            case COUNSELOR:
                if ($duelNumber > 1) {
                    $previousDuelCards = $duels[$duelNumber - 1];
                    $this->tokenManager->addSignetOnCard($previousDuelCards[$playerId]->id, $previousDuelCards[$opponentId]->id);
                    //todo check if other ongoin power
                }
                break;
        }
    }

    /**
     * @param array<mixed, array<mixed, object|null>> $duels 
     * @return void 
     */
    function getTiedDuels($duels) {
        $tied = $duels;
        $cardsWithSignet = array_map(fn($s) => $s->location_arg, $this->tokenManager->getSignetsOnCards());
        foreach ($duels as $duelNumber => $duel) {
            $duelCards = array_values($duel);
            $tie = !$this->array_some($duelCards, function ($c) use ($cardsWithSignet) {
                return in_array($c->id, $cardsWithSignet);
            });
            if (!$tie) {
                unset($tied[$duelNumber]);
            }
        }
        $this->dump('*******************getTiedDuels', $tied);
        return $tied;
    }

    /**
     * 
     * @param CardiaCard $interactiveAbility 
     * @param null|Faction $faction 
     * @param CardiaCard[] $cards 
     * @param null|string $option 
     * @return void 
     * @throws feException 
     * @throws BgaUserException 
     * @throws BgaSystemException 
     * @throws RandomException 
     */
    function applyInteractiveAbility(CardiaCard $interactiveAbility, ?Faction $faction, array $cards, ?string $option) {

        $this->notifyWithName('msg', clienttranslate('${cardName} ability'), [
            'cardName' => $interactiveAbility->name,
        ]);
        $this->dump('*******************applyInteractiveAbility', $interactiveAbility->name);
        foreach ($cards as $card) {
            $this->dump('*******************on', $card?->name);
        }

        $opponentTypeArg = $interactiveAbility->type_arg == 1 ? 2 : 1;
        $opponentId = $this->getPlayerIdFromPosition($opponentTypeArg);
        $playerId = $this->getPlayerIdFromPosition($interactiveAbility->type_arg);
        $card = $cards[0] ?? null;
        switch ($interactiveAbility->type) {
            case PALACE_GUARD:
                //faction has been chosen but the opponent still needs to choose a card
                if ($this->isAbilityPossible($interactiveAbility, $opponentId, $faction)) {
                    $this->globals->set(GLB_PLAYER_TO_ACTIVATE, $opponentId);
                    $this->globals->set(GLB_STEP_2, true);
                    $this->gamestate->nextState('interactiveAbilityStep2');
                }
                break;
            case INVENTOR:
                //first selected card gets a +3
                $this->cardManager->incCardModifier($card, 3);
                $this->globals->set(GLB_INVENTOR_PLUS_CARD, $card->id);
                //still needs to select another card
                $this->globals->set(GLB_STEP_2, true);
                $this->gamestate->nextState('interactiveAbilityStep2');
                break;
            case VOID_MAGE:
                if ($option == "removeModifiers") {
                    $this->cardManager->updateCardModifier($card, 0);
                    $this->evaluateDuelValues([$card, $this->cardManager->getOpposingCard($card, $this->cardManager->getDuelsList())]);
                } else {
                    $this->tokenManager->discardTokenOfTypeOnCard($card, TokenType::ONGOING);
                    //todo reevaluate everything
                }
                $this->gamestate->nextState('finishDuel');
                break;
            case AMBUSHER:
                $involvedCards = $this->cardManager->getFactionCardsInHand($opponentId, $faction);
                foreach ($involvedCards as $c) {
                    $this->cardManager->discardCard($opponentId, $c->id, clienttranslate('${player_name} discards ${cardName}'), ["cardName" => $c->name, "player_name" => $this->getPlayerName($opponentId)]);
                    $this->cardManager->replenishHands();
                }
                $this->gamestate->nextState('finishDuel');
                break;
            case SWAMP_GUARDIAN:
                $encounter = $card->location_arg;
                $opposingCard = $this->cardManager->getOpposingCard($card, $this->cardManager->getDuelsList());
                $this->cardManager->discardDuelCard($opposingCard);
                $this->cardManager->moveCardToLocation($card, MATERIAL_LOCATION_HAND, $playerId, true, $playerId);
                $this->cardManager->reorderDuels($encounter);
                $this->gamestate->nextState('finishDuel');
                break;
            case MAGISTRA:
                $encounter = $card->location_arg;
                $this->globals->set(GLB_ABILITY_TO_RESOLVE, $card->id);
                $this->stLooserAbility();
                break;
            case PRODIGY:
                $this->cardManager->incCardModifier($card, 3);
                $this->evaluateDuelValues([$card, $this->cardManager->getOpposingCard($card, $this->cardManager->getDuelsList())]);
                $this->gamestate->nextState('finishDuel');
                break;
            case KINESIS_MAGE:
                $this->globals->set(GLB_KINESIS_SOURCE_CARD, $card->id);
                //still needs to select destination
                $this->globals->set(GLB_STEP_2, true);
                $this->gamestate->nextState('interactiveAbilityStep2');
                break;
            case ENVOY:
                $value = -3;
                if ($card) {
                    $this->cardManager->incCardModifier($card, $value);
                    $this->evaluateDuelValues([$card, $this->cardManager->getOpposingCard($card, $this->cardManager->getDuelsList())]);
                } else {
                    $this->globals->set(GLB_NEXT_CARD_MODIFIER . $playerId, $value);
                    $this->notifyWithName('nextCardModifier', "", [
                        'value' => $value,
                        'playerId' => $playerId,
                        'playerPosition' => $this->getPlayerPosition($playerId),
                    ]);
                }
                $this->gamestate->nextState('finishDuel');
                break;
            case WITCH_KING:
                $involvedCards = $this->cardManager->getFactionCardsInHand($opponentId, $faction);
                foreach ($involvedCards as $c) {
                    $this->cardManager->discardCard($opponentId, $c->id, clienttranslate('${player_name} discards ${cardName}'), ["cardName" => $c->name, "player_name" => $this->getPlayerName($opponentId)]);
                }
                $involvedCards = $this->cardManager->getFactionCardsInDeck($opponentId, $faction);
                foreach ($involvedCards as $c) {
                    $this->cardManager->discardCard($opponentId, $c->id, clienttranslate('${player_name} discards ${cardName}'), ["cardName" => $c->name, "player_name" => $this->getPlayerName($opponentId)]);
                }
                $this->cardManager->shuffleLocationByTypeArg(MATERIAL_LOCATION_DECK, $opponentTypeArg);
                $this->gamestate->nextState('finishDuel');
                break;
            case REVOLUTIONARY:
                foreach ($cards as $c) {
                    $this->cardManager->discardCard($opponentId, $c->id, clienttranslate('${player_name} discards ${cardName}'), ["cardName" => $c->name, "player_name" => $this->getPlayerName($opponentId)]);
                }
                $this->cardManager->addCardsToHand(2, $opponentId, $opponentTypeArg, true);
                $this->gamestate->nextState('finishDuel');
                break;
            case SUCCESSOR:
                $involvedCards = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $opponentTypeArg, MATERIAL_LOCATION_HAND);
                foreach ($involvedCards as $c) {
                    if (!in_array($c->id, array_map(fn($paramCard) => $paramCard->id, $cards))) {
                        $this->cardManager->discardCard($opponentId, $c->id, clienttranslate('${player_name} discards ${cardName}'), ["cardName" => $c->name, "player_name" => $this->getPlayerName($opponentId)]);
                    }
                }
                $involvedCards = $this->cardManager->getCardsOfTypeArgFromLocation(TABLE_CARD, $opponentTypeArg, MATERIAL_LOCATION_DECK);
                foreach ($involvedCards as $c) {
                    $this->cardManager->discardCard($opponentId, $c->id, clienttranslate('${player_name} discards ${cardName}'), ["cardName" => $c->name, "player_name" => $this->getPlayerName($opponentId)]);
                }
                $this->gamestate->nextState('finishDuel');
                break;
        }
    }

    function applyInteractiveAbilityStep2(CardiaCard $interactiveAbility, ?CardiaCard $card) {
        switch ($interactiveAbility->type) {
            case PALACE_GUARD:
                if ($card) {
                    $this->cardManager->discardDuelCard($card);
                    $this->cardManager->replenishHands();
                } else {
                    //add +7 influence
                    $this->cardManager->incCardModifier($interactiveAbility, 7);
                    $this->evaluateDuelValues([$interactiveAbility, $this->cardManager->getOpposingCard($interactiveAbility, $this->cardManager->getDuelsList())]);
                }
                break;
            case INVENTOR:
                //second selected card gets a -3
                $this->cardManager->incCardModifier($card, -3);
                $firstModif = $this->cardManager->getCard($this->globals->get(GLB_INVENTOR_PLUS_CARD));
                $this->globals->delete(GLB_INVENTOR_PLUS_CARD);
                $this->evaluateDuelValues([$card, $this->cardManager->getOpposingCard($card, $this->cardManager->getDuelsList())]);
                if ($firstModif->location_arg != $card->location_arg) {
                    $this->evaluateDuelValues([$firstModif, $this->cardManager->getOpposingCard($firstModif, $this->cardManager->getDuelsList())]);
                }
                break;
            case KINESIS_MAGE:
                $source = $this->cardManager->getCard($this->globals->get(GLB_KINESIS_SOURCE_CARD));
                $destination = $card;
                $modifiers = $this->cardManager->getModifierValueOnCard($source->id);
                $duels = $this->cardManager->getDuelsList();
                $reevaluate = false;
                if ($modifiers != 0) {
                    $this->cardManager->updateCardModifier($source, 0);
                    $this->cardManager->updateCardModifier($destination, $modifiers);
                    $reevaluate = true;
                }
                $tokens = $this->tokenManager->getOngoingTokensOnCards($source->id);
                if ($tokens) {
                    $reevaluate = true;
                    foreach ($tokens as $token) {
                        $this->tokenManager->discardTokenOfTypeOnCard($source, TokenType::ONGOING);
                        $this->tokenManager->addOngoingTokenOnCard($destination->id);
                    }
                }

                if ($reevaluate) {
                    $this->evaluateDuelValues([$destination, $this->cardManager->getOpposingCard($destination, $duels)]);
                    $this->evaluateDuelValues([$source, $this->cardManager->getOpposingCard($source, $duels)]);
                }
                break;
        }
        $this->gamestate->nextState('finishDuel');
    }

    function isAbilityPossible(CardiaCard $card, int $playerToApply, ?Faction $faction): bool {
        $cardOwner = $this->getPlayerIdFromPosition($card->type_arg);
        switch ($card->type) {
            case PALACE_GUARD:
                return !$faction || count($this->cardManager->getFactionCardsInHand($playerToApply, $faction)) > 0;
            case MAGISTRA:
                return !empty($this->getSelectableCards($card, $cardOwner));
            case SWAMP_GUARDIAN:
                return !empty($this->getSelectableCards($card, $cardOwner));
            default:
                return true;
        }
    }

    function discardDuelCard(CardiaCard $card, $msg = "", $msgArgs = []) {
        $this->tokenManager->discardTokensOnDuelCard($card);
        $this->cardManager->discardDuelCard($card);
        $this->notifyWithName('msg', $msg ? $msg : clienttranslate('${cardName} is discarded'), [
            'cardName' => $card->name,
            'i18n' => ['cardName', "cardName2"],
            ...$msgArgs
        ]);
    }

    function getCardValue(CardiaCard &$card, bool $withModifiers = true): int {
        $value = $card->value;
        //$this->dump('*************getCardValue', $card);
        //$this->dump('*************initial value**', $value);
        if ($withModifiers) {
            $applies = true;
            if ($applies) {
                $value += $this->cardManager->getModifierValueOnCard($card->id);
                $card->modifiedValue = $value;
            }
        }

        $this->dump('*************card**', $card->name);
        $this->dump('*************final value**', $value);
        return $value;
    }

    function stActivatePlayersToChooseDuelCard() {
        $abilityId = $this->globals->get(GLB_ABILITY_TO_RESOLVE);
        $ability = $abilityId ? $this->cardManager->getCard($abilityId, true) : null;
        if ($ability && $ability->type == FORTUNE_TELLER) {
            $this->gamestate->setPlayersMultiactive([$this->getPlayerIdFromPosition($ability->type_arg)], "duelReveal", true);
        } else {
            $this->gamestate->setAllPlayersMultiactive();
        }
    }

    /**
     * If only player has 5 signets or both have at least 5 signets but one player has more than the other, end of round.
     * @return void 
     */
    function stFinishDuel(array $winners = null, bool $everyoneLooses = false) {
        //add engineer influence if any
        $duels = $this->cardManager->getDuelsList();
        $finishingDuel = array_pop($duels);
        $anyModif = false;
        foreach ($this->getPlayers() as $playerId => $players) {
            $modifierToAdd = $this->globals->get(GLB_NEXT_CARD_MODIFIER_AFTER_ABILITY_TRIGGERED . $playerId, 0);
            if ($modifierToAdd != 0 && $finishingDuel[$playerId]->type != ENGINEER) {
                $anyModif = true;
                $this->cardManager->incCardModifier($finishingDuel[$playerId], $modifierToAdd);
                $this->globals->delete(GLB_NEXT_CARD_MODIFIER_AFTER_ABILITY_TRIGGERED . $playerId);
            }
        }
        if ($anyModif) {
            $this->evaluateDuelValues(array_values($finishingDuel));
        }

        //reset data
        $this->globals->delete(GLB_SELECTED_CARD_ID);
        $this->globals->delete(GLB_SELECTED_FACTION);
        $this->globals->delete(GLB_STEP_2);

        // $winner = $winners ?? $this->getSignetCountWinner();
        if (!$winners && !$everyoneLooses) {
            $winners = [];
            $withCard = $this->getNoPlayableCardWinner();
            if ($withCard && $withCard > -1) {
                $winners = [$withCard];
            } else {
                if ($withCard && $withCard == -1) {
                    self::notifyAllPlayers('msg', clienttranslate('No more cards to play for any player and tie on signets count, end of round'), []);
                }
            }
        }

        if ($everyoneLooses) {
            $winners = [];
            self::notifyAllPlayers('msg', clienttranslate('Everyone looses, end of round'), []);
        }

        $this->dump('*******************winners', $winners);
        $nextState = $winners || $everyoneLooses ? 'nextRound' : 'chooseDuelCard';

        if ($winners) {
            foreach ($winners as $winner) {
                if ($winner) {
                    $this->setRoundWinner($winner);
                }
            }
        } else {
            $abilityId = $this->globals->get(GLB_ABILITY_TO_RESOLVE);
            if ($abilityId) {
                $ability = $this->cardManager->getCard($abilityId);
                if ($ability->type == FORTUNE_TELLER) {
                    $nextState = 'chooseFortuneTellerCard';
                    $this->globals->set(GLB_PLAYER_TO_ACTIVATE, $this->getOpponentId($this->getPlayerIdFromPosition($ability->type)));
                }
            }

            $location = $this->getScenery();
            if ($location == BAZAAR) {
                $this->cardManager->replenishHands();
            } else {
                $this->cardManager->pickAdditionalCard();
            }

            if ($location == GRAND_LIBRARY || $location == SCRAPYARD) {
                $this->cardManager->pickAdditionalCard(); //get one more card
            }

            if ($location == SCRAPYARD) {
                $this->globals->set(GLB_NEXT_STATE_AFTER_SCRAPYARD, $nextState);
                $nextState = 'chooseScrapyardCard';
            }
        }
        $this->gamestate->nextState($nextState);
    }

    function setRoundWinner(int $playerId) {
        $playerName = $this->getPlayerName($playerId);
        $this->incPlayerScore($playerId, 1, clienttranslate('${player_name} wins the round !'), ["player_name" => $playerName]);
        $this->notifyAllPlayers('importantMessage', "", ["message" => clienttranslate('${player_name} wins the round'), "type" => "POSITIVE", "temporary" => true, "player_name" => $playerName]);
    }

    function getSignetCountWinner(): ?int {
        $playersIds = $this->getPlayersIds();
        $signetCounts = array_combine($playersIds, array_map(fn($id) => $this->tokenManager->getSignetCount($id), $playersIds));
        $winner = null;

        //filter players with at least 5 signets
        $playersWith5Signets = array_filter($signetCounts, fn($count) => $count >= 5);

        //check if every player from playersWith5Signets has the same signets count
        $tieOn5SignetsOrMore = count(array_unique($playersWith5Signets)) === 1;
        if (!$playersWith5Signets || $tieOn5SignetsOrMore) {
            //no winner yet
        } else {
            if (count($playersWith5Signets) == 1) {
                //if only player has 5 signets, he wins
                $winner = array_key_first($playersWith5Signets);
            } else {
                //winner is the player with the most signets
                $maxSignets = max($playersWith5Signets);
                $winners = array_keys(array_filter($playersWith5Signets, fn($count) => $count == $maxSignets));
                $winner = $winners[0];
            }
        }
        return $winner;
    }

    /**
     * Returns id of the only one player who has cards or who has most signets in case of tie
     * return -1 if tie on no card and signets count
     * return null if everyone has cards
     * @return int|null 
     */
    function getNoPlayableCardWinner(): int|null {
        $winner = null;
        $playersIds = $this->getPlayersIds();

        //if no card in hand and no card in deck, won’t be able to play
        $cardsCount = array_combine($playersIds, array_map(
            fn($id) => $this->cardManager->countCardsOfTypeArgFromLocation(TABLE_CARD, $this->getPlayerPosition($id), MATERIAL_LOCATION_DECK)
                + $this->cardManager->countCardsOfTypeArgFromLocation(TABLE_CARD, $this->getPlayerPosition($id), MATERIAL_LOCATION_HAND),
            $playersIds
        ));

        //if only one player has cards, he wins
        $playersWithCards = array_filter($cardsCount, fn($count) => $count > 0);
        if (count($playersWithCards) == 1) {
            $winner = array_key_first($playersWithCards);
        } else if (count($playersWithCards) == 0) {
            //if no player has cards, the player with the most signets wins
            $playersIds = $this->getPlayersIds();
            $signetCounts = array_combine($playersIds, array_map(fn($id) => $this->tokenManager->getSignetCount($id), $playersIds));
            $maxSignets = max($signetCounts);
            $winners = array_keys(array_filter($signetCounts, fn($count) => $count == $maxSignets));
            if (count($winners) == 1) {
                $winner = $winners[0];
            } else {
                $winner = -1;
            }
        }
        return $winner;
    }

    function stNextRound() {
        $players = $this->loadPlayersBasicInfos();
        $currentRound = $this->globals->get(GLBL_ROUND);

        if ($this->hasReachedEndOfGameRequirements()) {
            if ($this->isStudio()) {
                $this->gamestate->nextState('debugEndGame');
            } else {
                $this->gamestate->nextState('endGame');
            }
        } else {
            $this->globals->set(GLB_DUEL_COUNT, 0);
            $this->globals->inc(GLBL_ROUND, 1);
            $currentRound++;

            self::notifyAllPlayers('newRound', clienttranslate('&#10148; Round ${round}'), ["round" => $currentRound]);
            $this->cardManager->resetDecks();
            $this->tokenManager->resetTokens();
            $this->notifyCounterChange();
            $this->gamestate->nextState('chooseDuelCard');
        }
    }

    function endOfRoundReset() {
        $this->globals->set(GLB_DUEL_COUNT, 0);
    }

    function hasReachedEndOfGameRequirements(): bool {
        $playersIds = $this->getPlayersIds();
        foreach ($playersIds as $playerId) {
            if ($this->getPlayerScore($playerId) == 2) {
                return true;
            }
        }
        return false;
    }

    function stNextPlayer() {
        $playerId = $this->getActivePlayerId();
        if (!$playerId) {
            $this->activateNextPlayerCustom();
            $this->gamestate->nextState('nextPlayer');
            return;
        }

        //$this->setGameStateValue(TICKETS_USED, 0);
        $lastTurn = intval($this->getGameStateValue(LAST_TURN));

        // check if it was last action from the last player or if there is no arrow left
        if ($lastTurn == $playerId || ($this->hasReachedEndOfGameRequirements($playerId) && $this->isLastPlayer($playerId))) {
            $this->gamestate->nextState('endScore');
        } else {
            //finishing round or playing normally
            $this->activateNextPlayerCustom();
            $this->gamestate->nextState('nextPlayer');
        }
    }

    /**
     * Activates next player, also giving him extra time.
     */
    function activateNextPlayerCustom() {
        $player_id = $this->activeNextPlayer();
        $this->giveExtraTime($player_id);
        $this->incStat(1, 'turns_number', $player_id);
        $this->incStat(1, 'turns_number');
        $this->notifyWithName('msg', clienttranslate('&#10148; Start of ${player_name}\'s turn'));
        //$this->makeSavepoint();
    }

    function stEndScore() {
        $this->score();

        if ($this->isStudio()) {
            $this->gamestate->nextState('debugEndGame');
        } else {
            $this->gamestate->nextState('endGame');
        }
    }

    function score() {
        $sql = "SELECT player_id id, player_score score, player_no playerNo FROM player ORDER BY player_no ASC";
        $players = $this->getCollectionFromDb($sql);

        // points gained during the game
        $totalScore = [];
        foreach ($players as $playerId => $playerDb) {
            $totalScore[$playerId] = intval($playerDb['score']);
        }

        //end of game points

        // failed destinations 
        /* $destinationsResults = [];
        $completedDestinationsCount = [];
        foreach ($players as $playerId => $playerDb) {
            $completedDestinationsCount[$playerId] = 0;
            $uncompletedDestinations = [];
            $completedDestinations = [];

            $destinations = $this->getDestinationsFromDb($this->destinations->getCardsInLocation('hand', $playerId));

            foreach ($destinations as &$destination) {
                $completed = boolval($this->getUniqueValueFromDb("SELECT `completed` FROM `destination` WHERE `card_id` = $destination->id"));
                if ($completed) {
                    $completedDestinationsCount[$playerId]++;
                    $completedDestinations[] = $destination;
                    $this->incStat(1, STAT_POINTS_WITH_PLAYER_COMPLETED_DESTINATIONS, $playerId);
                } else {
                    $totalScore[$playerId] += -1;
                    $this->incScore($playerId, -1);
                    if ($this->isDestinationRevealed($destination->id)) {
                        $totalScore[$playerId] += -1;
                        $this->incScore($playerId, -1);
                        $this->incStat(-1, STAT_POINTS_WITH_REVEALED_DESTINATIONS, $playerId);
                    }
                    $this->incStat(1, STAT_POINTS_LOST_WITH_UNCOMPLETED_DESTINATIONS, $playerId);
                    $uncompletedDestinations[] = $destination;
                }
            }

            $destinationsResults[$playerId] = $uncompletedDestinations;
        }
*/
        foreach ($players as $playerId => $playerDb) {
            static::DbQuery("UPDATE player SET `player_score` = $totalScore[$playerId] where `player_id` = $playerId");
            static::DbQuery("UPDATE player SET `player_score_aux` = `player_remaining_tickets` where `player_id` = $playerId");
        }

        $bestScore = max($totalScore);
        $playersWithScore = [];
        foreach ($players as $playerId => &$player) {
            $player['playerNo'] = intval($player['playerNo']);
            $player['ticketsCount'] = $this->getRemainingTicketsCount($playerId);
            $player['score'] = $totalScore[$playerId];
            $playersWithScore[$playerId] = $player;
        }
        $this->notifyAllPlayers('bestScore', '', [
            'bestScore' => $bestScore,
            'players' => array_values($playersWithScore),
        ]);

        // highlight winner(s)
        foreach ($totalScore as $playerId => $playerScore) {
            if ($playerScore == $bestScore) {
                $this->notifyAllPlayers('highlightWinnerScore', '', [
                    'playerId' => $playerId,
                ]);
            }
        }
    }
}
