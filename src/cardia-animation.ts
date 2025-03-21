/**
 * Base class for animations.
 */
abstract class CardiaAnimation {
	protected zoom: number

	constructor(protected game: CardiaGame) {
		this.zoom = this.game.getZoom()
	}

	public abstract animate(): Promise<CardiaAnimation>
}
