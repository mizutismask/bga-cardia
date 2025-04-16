/**
 * Player table.
 */
class PlayerTable {
	
	public handStock: LineStock<CardiaCard>

	constructor(private game: CardiaGame, player: CardiaPlayer) {
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
			this.initHand(player)
		}
	}

	private initHand(player: CardiaPlayer) {
		this.handStock = new LineStock<CardiaCard>(this.game.cardsManager, $('hand-' + player.id), {})
		this.handStock.setSelectionMode('single')
		this.handStock.addCards(player.hand) //, { originalSide: "back" }, {visible:false}
	}
}
