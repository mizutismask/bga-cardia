/**
 * Player table.
 */
class PlayerTable {
	
	public handStock: LineStock<CardiaCard>

	constructor(private game: CardiaGame, player: CardiaPlayer, cards: CardiaCard[]) {
		const isMyTable = player.id === game.getPlayerId().toString()
		const ownClass = isMyTable ? 'own' : ''
		let html = `
			<a id="anchor-player-${player.id}"></a>
            <div id="player-table-${player.id}" class="player-order${player.playerNo} player-table ${ownClass}">
            </div>
        `
		dojo.place(html, 'player-tables')

		if (isMyTable) {
			const handHtml = `
			<div id="hand-${player.id}" class="nml-player-hand"></div>
        `
			dojo.place(handHtml, `player-table-${player.id}`, 'first')
			this.initHand(player, cards)
		}
	}

	private initHand(player: CardiaPlayer, cards: CardiaCard[] = []) {
		this.handStock = new LineStock<CardiaCard>(this.game.cardsManager, $('hand-' + player.id), {sort: sortFunction('value'),})
		this.handStock.setSelectionMode('single')
		if (cards) {
			this.handStock.addCards(cards) //, { originalSide: "back" }, {visible:false}
		}
		this.handStock.onSelectionChange = (selection: CardiaCard[], lastChange: CardiaCard) => {
			this.game.handSelectionChange(selection, lastChange)
		}
	}
}
