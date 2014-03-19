// Main JS File For Guest App

(function() {
	
	// Create the MsGuestapp namespace
	if (typeof MsGuestapp == 'undefined') {
		window["MsGuestapp"] = {};	// global Object container to create namespace
	}
	
	/* MsPageManager
	 * Object to dynamically load JS and CSS files at run-time (i.e. after Ajax calls)
	 */
	function MsPageManager() {
		this.filesAdded = '' // list of files already added. Tracked so we avoid adding duplicate files to the DOM
	}
	
	MsPageManager.prototype.addCss = function(filepath) {
		if (this.filesAdded.indexOf("[" + filepath + "]") == -1) { // file not added yet
			var fileref = document.createElement('link');
			fileref.setAttribute("rel", "stylesheet");
			fileref.setAttribute("type", "text/css");
			fileref.setAttribute("href", filepath);
			
			// put in file added list
			this.filesAdded += "[" + filepath + "]";
			
			// add to DOM
			if (typeof fileref != "undefined")
				document.getElementsByTagName("head")[0].appendChild(fileref);
		}
	};
	MsPageManager.prototype.addJs = function(filepath) {
		if (this.filesAdded.indexOf("[" + filepath + "]") == -1) { // file not added yet
			var fileref = document.createElement('script');
			fileref.setAttribute("type", "text/javascript");
			fileref.setAttribute("src", filepath);
			
			// put in file added list
			this.filesAdded += "[" + filepath + "]";
			
			// add to DOM
			if (typeof fileref != "undefined")
				document.getElementsByTagName("head")[0].appendChild(fileref);
		}
	};
	
	MsGuestapp.pageManager = new MsPageManager(); // single instance of this object, to be used by all pages
	
	
	/* MsSliderRegistry
	 *
	 */
	function MsSliderRegistry() {
		this.sliders = {}; // collection of slider instances
	}
	
	MsSliderRegistry.prototype.register = function(page_id, album_id) {
		MsGuestapp.pageManager.addJs("/assets/js/vendor/jquery.bxslider.min.js");
 		MsGuestapp.pageManager.addCss("/assets/css/vendor/jquery.bxslider.css");
		if (typeof this.sliders[page_id + '-' + album_id] == "undefined") {
			var slider = new MsSlider(page_id, album_id);
			this.sliders[page_id + '-' + album_id] = slider;
			slider.activate();
		}
	};
	
	MsGuestapp.sliderRegistry = new MsSliderRegistry(); // single instance of this object, to be used by all pages
	
	
	/* MsSlider
	 * Represents an album slider. Wraps a bxSlider instance
	 */
	function MsSlider(page_id, album_id) {
		this.pageId = page_id;
		this.albumId = album_id;
		this.cursor = 0; // slider slot which is currently being viewed	
		this.rLimit = 0; // right-hand limit of the preload window
		this.lLimit = 0; // left-hand limit of the preload window
		this.bxSliderInstance = null;
	}
	
	MsSlider.prototype.activate = function() {
		if (this.bxSliderInstance == null) {
			if (typeof $.fn.bxSlider == "undefined") { // if slider library isn't loaded yet, delay
				var self = this;
				setTimeout(function() {
					self.activate();
				}, 100);
			} else {
				var slider_config = {
					startSlide: 5,
					pager: false,
					onSlideBefore: function($slideElement, oldIndex, newIndex){
						
					},
					onSlideAfter: function($slideElement, oldIndex, newIndex) {
						// redraw if slide is larger than the viewport
						if ($slideElement.height() > $slideElement.parent().parent().height()) {
							$slideElement.parent().parent().css('height', $slideElement.height());
						}
						
						this.cursor = newIndex;
						alert(this.cursor);
					}
				};
				
				this.bxSliderInstance = $('#album' + this.albumId).bxSlider(slider_config);
				this.cursor = 5;
				this.rLimit = 8;
				this.lLimit = 2;
				
			}
		}
	}
	
	
	

})();