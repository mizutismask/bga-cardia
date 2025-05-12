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
		dojo.place(html, 'custom-game-area', 'first')
		this.initDuelStocks(duels)
		this.updateCssVariables()
	}

	private initDuelStocks(duels: DuelsList): void {
		Object.entries(duels).forEach(([duelNumber, duel]) => {
			this.createDuelStock(parseInt(duelNumber), duel)
			this.duelCounter++
		})
		//creates additional stock in case there are tokens on future cards
		//this.createDuelStock(null, null)
	}

	private updateCssVariables() {
		document.documentElement.style.setProperty('--duels-count', this.duelCounter.toString())// Math.max(8,this.duelCounter).toString())
	}

	public createDuelStock(duelNumber: number, duel: { [playerId: string]: CardiaCard }): string {
		let duelId = duelNumber
		if (!duelNumber) {
			this.duelCounter++
			duelId = this.duelCounter
		}
		dojo.place(`<div id='duel-${duelId}' class="duel-wrapper"></div>`, 'central-zone')
		this.duelStocks[duelId] = new SlotStock<CardiaCard>(this.game.cardsManager, $('duel-' + duelId), {
			slotsIds: this.getSlotsWithCurrentPlayerFirst(),
			mapCardToSlot: (card) => `${card.type_arg}`
		})
		this.duelStocks[duelId].setSelectionMode('none')
		this.duelStocks[duelId].onSelectionChange= (selection: Array<CardiaCard>, lastChange: CardiaCard) => {
			if (selection) {
				//deselect all other stocks
				this.duelStocks.forEach(stock => { if(stock != this.duelStocks[duelId]) stock.unselectAll(true) })
			}
		}
		this.updateCssVariables()
		if (duel) {
			this.duelStocks[duelId].addCards(Object.values(duel))
		}

		//add vs icon
		dojo.place(`<div class="vs-icon">VS</div>`, 'duel-' + duelId)
		return 'duel-' + duelId
	}

	private getSlotsWithCurrentPlayerFirst() {
		if (this.game.isNotSpectator()) {
			const myOrder = this.game.getCurrentPlayer().playerNo
			return [myOrder, myOrder == 1 ? 2 : 1]
		} else {
			return [1, 2]
		}
	}

	public resetDuelStocks() {
		this.duelStocks.forEach((stock, i) => {
			stock.removeAll()
			this.game.cardsManager.removeStock(stock)
			dojo.destroy('duel-' + i)
		})
		this.duelStocks = []
		this.duelCounter = 0
		this.initDuelStocks([])
	}
}
