/**
 * Framework interfaces
 */

interface Game {
	
	setup: (gamedatas: any) => void
	onEnteringState: (stateName: string, args: any) => void
	onLeavingState: (stateName: string) => void
	onUpdateActionButtons: (stateName: string, args: any) => void
	setupNotifications: () => void
	format_string_recursive: (log: string, args: any[]) => void;
}

interface StatusBar {
	setTitle(title: string, args?: object): void
	addActionButton(
		label: string,
		callback: Function,
		params: {
			color?: 'primary' | 'secondary' | 'alert'
			id?: string
			classes?: string
			destination?: HTMLElement
			title?: string
		}
	): HTMLButtonElement
	removeActionButtons(): void
}

interface Notif<T> {
	args: T
	log: string
	move_id: number
	table_id: string
	time: number
	type: string
	uid: string
}

/* TODO repace Function by (..params) => void */
interface Dojo {
	attr: Function
	create: Function
	place: (html: string, nodeId: string | HTMLElement, action?: string) => void
	style: Function
	hitch: Function
	hasClass: (nodeId: string, className: string) => boolean
	addClass: (nodeId: string | HTMLElement, className: string) => void
	removeClass: (nodeId: string | HTMLElement, className?: string) => void
	toggleClass: (nodeId: string | HTMLElement, className: string, forceValue?: boolean) => void
	connect: (node: HTMLElement, eventType: string, callback: Function) => any //return handle
	disconnect: Function
	query: Function
	subscribe: Function
	string: any
	fx: any
	marginBox: Function
	position: Function
	contentBox: Function
	clone: Function
	fadeIn: Function
	trim: Function
	stopEvent: (evt) => void
	destroy: (nodeId: string) => void
	forEach: Function
	xhrGet: Function
	empty: (nodeId: string) => void
	byId: (nodeId: string | HTMLElement) => HTMLElement
}

interface Player {
	beginner: boolean
	color: string
	color_back: any | null
	eliminated: number
	id: string
	is_ai: string
	name: string
	score: string
	zombie: number
}
