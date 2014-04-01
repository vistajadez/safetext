// SafeText Main JS Startup File

(function() {
	
	// Create the Safetext namespace
	if (typeof Safetext == 'undefined') {
		window["Safetext"] = {};	// global Object container to create namespace
	}
	
	
	/**
	 *	Cookie Handling
	 */
	function setCookie(c_name, value, exdays) {
	    var exdate = new Date();
	    exdate.setDate(exdate.getDate() + exdays);
	    var c_value = escape(value) + ((exdays == null) ? "" : "; expires=" + exdate.toUTCString());
	    document.cookie = c_name + "=" + c_value  + '; path=/;';
	    
	}
	
	function getCookie(c_name) {
	    var i, x, y, ARRcookies = document.cookie.split(";");
	    for (i = 0; i < ARRcookies.length; i++) {
	        x = ARRcookies[i].substr(0, ARRcookies[i].indexOf("="));
	        y = ARRcookies[i].substr(ARRcookies[i].indexOf("=") + 1);
	        x = x.replace(/^\s+|\s+$/g, "");
	        if (x == c_name) {
	            return unescape(y);
	        }
	    }
	}
	
	
	/**
	 * MsPageManager
	 * Object to dynamically load JS and CSS files at run-time (i.e. after Ajax calls)
	 */
	function MsPageManager() {
		this.filesAdded = '' // list of files already added. Tracked so we avoid adding duplicate files to the DOM
	}
	
	MsPageManager.prototype.addCss = function(filepath) {
		if (this.filesAdded.indexOf("[" + filepath + "]") == -1) { // file not added yet
//			console.log('Adding CSS File: ' + filepath);
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
//			console.log('Adding JS File: ' + filepath);
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
	
	/**
	 * Tasks to run at every page change
	 * @param $("div") page
	 */
	MsPageManager.prototype.pageChange = function(page) {
		// Load any page-specific JS file
		if (typeof(page.attr("data-safetext-load-js")) != "undefined") Safetext.pageManager.addJs("/assets/js/" + page.attr("data-safetext-load-js"));
		
		
		
	};
	
	
	
	// ********* Hook Listeners to UI objects/events **********
	
	/**
	 * This function will run every time a page change occurs.
	 * 
	 */
	$(document).on( "pagecontainerhide", function( event, ui ) {
		Safetext.lastPage = ui.nextPage;	
		Safetext.pageManager.pageChange(ui.nextPage);
	});
	
	/**
	 * Log In.
	 * This event will run when the login form's Login button is clicked.
	 * Authenticates and logs a user into Safetext.
	 */
	$(document).on('click', '.safetext-login .safetext-login-button', function() {
		var thisForm = $(".safetext-login-form");
	
		if (thisForm.find('input[name="name"]').val() == '') {
			alert("Enter your account name"); 
			return false;
		}
		if (thisForm.find('input[name="password"]').val() == '') {
			alert("Enter your password"); 
			return false;
		}

			
		$.ajax({url: '/api/auth',
			data: 'device_signature=webclient&device_description=Web+Client',
			headers: {'x-safetext-username': thisForm.find('input[name="name"]').val(),'x-safetext-password': thisForm.find('input[name="password"]').val()},
			type: 'post',               
			async: 'true',
			dataType: 'json',
			beforeSend: function() {
				// This callback function will trigger before data is sent
				$.mobile.loading( 'show');
			},
			complete: function() {
				// This callback function will trigger on data sent/received complete
				$.mobile.loading( "hide" );
			},
			success: function (result) {
				if(result.status === 'success') {
					console.log('Token: ' + result.data.token);
					
					// set the auth cookie
					setCookie('token',result.data.token,7);
					
					// Go to dashboard
					window.location='/webclient/home';
					
					
				} else {
					alert('Unable to login. The server said: ' + result.data.message); 
				}
			},
			error: function (request,error) {
				// This callback function will trigger on unsuccessful action                
				alert('Network error has occurred; please try again.');
			}
		}); 
		
		return false;
	});
	
	
	/**
	 * Register.
	 * This event will run when the registration form's Register button is clicked.
	 * Registers a new user.
	 */
	$(document).on('click', '.safetext-register .safetext-register-button', function() {
		var thisForm = $(".safetext-register-form");
	
		if (thisForm.find('input[name="name"]').val() == '') {
			alert("Your name is required"); 
			return false;
		}
		if (thisForm.find('input[name="username"]').val() == '') {
			alert("Please select a username"); 
			return false;
		}
		if (thisForm.find('input[name="password"]').val() == '') {
			alert("Please select a password"); 
			return false;
		}
	
		$.ajax({url: '/api/users',
			data: 'device_signature=webclient&device_description=Web+Client&name=' + encodeURIComponent(thisForm.find('input[name="name"]').val()) + '&email=' + thisForm.find('input[name="email"]').val(),
			headers: {'x-safetext-username': thisForm.find('input[name="username"]').val(),'x-safetext-password': thisForm.find('input[name="password"]').val()},
			type: 'post',               
			async: 'true',
			dataType: 'json',
			beforeSend: function() {
				// This callback function will trigger before data is sent
				$.mobile.loading( 'show');
			},
			complete: function() {
				// This callback function will trigger on data sent/received complete
				$.mobile.loading( "hide" );
			},
			success: function (result) {
				if(result.status === 'success') {
					console.log('Token: ' + result.data.token);
					
					// set the auth cookie
					setCookie('token',result.data.token,7);
					
					// Go to dashboard
					window.location='/webclient/home';
					
				} else {
					alert('Unable to register. The server said: ' + result.data.message); 
				}
			},
			error: function (request,error) {
				// This callback function will trigger on unsuccessful action                
				alert('Network error has occurred; please try again.');
			}
		}); 
		
		return false;
	});
	
	
	
	
	/**
	 * Startup:
	 * The following will be run only for the initially loaded page.
	 * 
	 */
	$( document ).ready(function() {
		Safetext.startPage = $( document ).find("[data-role='page']");
		Safetext.lastPage = Safetext.startPage;
		
		Safetext.pageManager = new MsPageManager(); // single instance of this object, to be used by all pages		
		Safetext.pageManager.pageChange(Safetext.startPage); // run any pagechange tasks on the initial page	
	});
	
	
	
	
	
	
	
	
	
	

})();