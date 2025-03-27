/**
 * Duels list
 */
class CentralZone {
	public duelStocks: LineStock<CardiaCard>[] = []
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
		})
	}

	private createDuelStock(duelNumber: number, duel: { [playerId: string]: CardiaCard }): void {
		dojo.place(`<div id='duel-${duelNumber}'></div>`, 'central-zone')
		this.duelStocks[duelNumber] = new LineStock<CardiaCard>(this.game.cardsManager, $('duel-' + duelNumber), {})
		this.duelStocks[duelNumber].setSelectionMode('none')
		this.duelStocks[duelNumber].addCards(Object.values(duel))
	}
}
