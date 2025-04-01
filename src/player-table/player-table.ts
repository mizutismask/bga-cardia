/**
 * Player table.
 */
class PlayerTable {
	public discard: AllVisibleDeck<CardiaCard>
	public handStock: LineStock<CardiaCard>

	constructor(private game: CardiaGame, player: CardiaPlayer) {
		const isMyTable = player.id === game.getPlayerId().toString()
		const ownClass = isMyTable ? 'own' : ''
		let html = `
			<a id="anchor-player-${player.id}"></a>
            <div id="player-table-${player.id}" class="player-order${player.playerNo} player-table ${ownClass}">
				
				<span class="player-name" style="color:#${player.color}">${player.name}</span>
            </div>
        `
		dojo.place(html, 'player-tables')

		if (isMyTable) {
			const handHtml = `
			<div class="discard-wrapper"><div id="discard-${player.id}"></div></div>
			<div id="hand-${player.id}" class="nml-player-hand"></div>
			<div class="discard-wrapper"><div id="discard-${this.game.getOpponentId(player.id)}"></div></div>
        `
			dojo.place(handHtml, `player-table-${player.id}`, 'first')
			this.initHand(player)
		}
		this.initDiscard(player)
	}

	private initHand(player: CardiaPlayer) {
		this.handStock = new LineStock<CardiaCard>(this.game.cardsManager, $('hand-' + player.id), {})
		this.handStock.setSelectionMode('single')
		this.handStock.addCards(player.hand) //, { originalSide: "back" }, {visible:false}
	}

	private initDiscard(player: CardiaPlayer) {
		this.discard = new AllVisibleDeck<CardiaCard>(this.game.cardsManager, $('discard-' + player.id), {
			counter: {
				hideWhenEmpty: false,
				position: 'bottom',
				extraClasses: 'stock-counter',
				counterId: 'discard-counter-' + player.id
			}
		})
		this.discard.setSelectionMode('none')
		this.discard.addCards(player.discard)
		$('discard-counter-' + player.id).innerHTML = player.discard.length.toString()
	}
}
