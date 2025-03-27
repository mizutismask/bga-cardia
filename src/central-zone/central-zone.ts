/**
 * Duels list
 */
class CentralZone {
	public duelStocks: SlotStock<CardiaCard>[] = []
	private duelCounter: number = 0
	constructor(private game: CardiaGame, duels: DuelsList) {
		let html = `
            <div id="central-zone" class="central-zone">
            </div>
        `
		dojo.place(html, 'custom-game-area')
		this.initDuelStocks(duels)
	}

	private initDuelStocks(duels: DuelsList): void {
		Object.entries(duels).forEach(([duelNumber, duel]) => {
			this.createDuelStock(parseInt(duelNumber), duel)
			this.duelCounter++
		})
		//creates additional stock in case there are tokens on future cards
		this.createDuelStock(null, null)
	}

	public createDuelStock(duelNumber: number, duel: { [playerId: string]: CardiaCard }): void {
		let duelId = duelNumber
		if (!duelNumber) {
			this.duelCounter++
			duelId = this.duelCounter
		}
		dojo.place(`<div id='duel-${duelId}'></div>`, 'central-zone')
		this.duelStocks[duelId] = new SlotStock<CardiaCard>(this.game.cardsManager, $('duel-' + duelId), {
			slotsIds: this.getSlotsWithCurrentPlayerFirst(),
			mapCardToSlot: (card) => `${card.type_arg}`
		})
		this.duelStocks[duelId].setSelectionMode('none')
		if (duel) {
			this.duelStocks[duelId].addCards(Object.values(duel))
		}
	}

	private getSlotsWithCurrentPlayerFirst() {
		if (this.game.isNotSpectator()) {
			const myOrder = this.game.getCurrentPlayer().playerNo
			return [myOrder, myOrder == 1 ? 2 : 1]
		} else {
			return [1, 2]
		}
	}
}
