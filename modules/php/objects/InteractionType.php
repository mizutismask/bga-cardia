<?php
namespace Bga\Games\Cardia\objects;
enum InteractionType: string
{
    case selectCardFromHand = 'selectCardFromHand';
    case selectCardFromDuels = 'selectCardFromDuels';
    case selectFaction = 'selectFaction';
}
?>