window.scroller = null;

function init(){
	window.scroller = new SmoothScroll(document,220,24);
}

function SmoothScroll(target, speed, smooth) {
	if (target === document)
		target = (document.scrollingElement 
              || document.documentElement 
              || document.body.parentNode 
              || document.body) // cross browser support for document scrolling
      
	var moving = false
	var pos = target.scrollTop
  var frame = target === document.body 
              && document.documentElement 
              ? document.documentElement 
              : target // safari is the new IE
  
	var wheelOpt = { passive: false };
	if ('onwheel' in document.createElement('div')) {
		target.addEventListener('wheel', scrolled, wheelOpt);
	} else {
		target.addEventListener('mousewheel', scrolled, wheelOpt);
		target.addEventListener('DOMMouseScroll', scrolled, wheelOpt);
	}

	this.setPostion = function(newPosY) {
		pos = newPosY;
		// Si no se está moviendo, inicia el bucle de animación para ir a la nueva posición.
		if (!moving) update();
	};
	this.isMoving = function() { return moving; };
	this.getDestination = function() { return pos; };
	this.stop = function() {
		moving = false;
		pos = target.scrollTop;
	};

	function scrolled(e) {
		// FIX DEFINITIVO Y ARQUITECTÓNICO:
		// Si el cursor está sobre el mapa interactivo de Leaflet (#map) o sobre cualquier panel
		// que tenga su propio scroll interno, apagamos SmoothScroll temporalmente para que 
		// no haya choques, dejando actuar al comportamiento nativo.
		if (e.target && e.target.closest) {
			if (e.target.closest('#map, .sheet-body, .search-list, .timeline-modal-content, .fp-drawer-content-wrapper, .candidato-sidebar, .marquee-container, #ft-chat-container')) return;
		}

		e.preventDefault(); // disable default scrolling

		var delta = normalizeWheelDelta(e)

		if (!moving) {
			pos = target.scrollTop;
		}
		pos += -delta * speed
		pos = Math.max(0, Math.min(pos, target.scrollHeight - frame.clientHeight)) // limit scrolling

		if (!moving) update()
	}

	function normalizeWheelDelta(e){
		if (e.type === 'wheel') {
			var delta = -e.deltaY;
			if (e.deltaMode === 0) return delta / 120; // Píxeles
			return e.deltaMode === 1 ? delta / 3 : delta; // Líneas o páginas
		}
		if(e.detail){
			if(e.wheelDelta)
				return e.wheelDelta/e.detail/40 * (e.detail>0 ? 1 : -1) // Opera
			else
				return -e.detail/3 // Firefox
		}else
			return e.wheelDelta/120 // IE,Safari,Chrome
	}

	function update() {
		moving = true
    
		var delta = (pos - target.scrollTop) / smooth
    
		target.scrollTop += delta
    
		if (Math.abs(delta) > 0.5)
			requestFrame(update)
		else
			moving = false
	}

	var requestFrame = function() { // requestAnimationFrame cross browser
		return (
			window.requestAnimationFrame ||
			window.webkitRequestAnimationFrame ||
			window.mozRequestAnimationFrame ||
			window.oRequestAnimationFrame ||
			window.msRequestAnimationFrame ||
			function(func) {
				window.setTimeout(func, 1000 / 50);
			}
		);
	}()
}