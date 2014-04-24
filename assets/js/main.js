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

		// If this is a conversation page, scroll to bottom automatically when page is loaded
		if (page.hasClass("safetext-conversation")) window.scrollTo(0,document.body.scrollHeight);
		
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
		var thisForm = $(".safetext-login-form").filter(":visible");
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
					//console.log('Token: ' + result.data.token);
					
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
		var thisForm = $(".safetext-register-form").filter(":visible");
	
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
					//console.log('Token: ' + result.data.token);
					
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
	 * New Message.
	 * This event will run when the new message composer's Send button is clicked
	 * Submits a new message to a contact.
	 */
	$(document).on('click', '.safetext-conversation .safetext-new-message-button', function() {
		var messageWindow = Safetext.lastPage.find(".safetext-new-message");
		if (messageWindow.val() != '') {
			var contactId = 	messageWindow.attr('data-safetext-contact');
			var messageText = 	messageWindow.val();
			var isDraft = 		messageWindow.attr('data-safetext-isdraft');
			var isImportant =	messageWindow.attr('data-safetext-isimportant');
			var lifetime = 		messageWindow.attr('data-safetext-lifetime');
			
			if (contactId > 0) {
				// call web service to send message
				$.ajax({url: '/api/messages',
					data: {
						"recipients": [contactId],
						"content": messageText,
						"is_important": isImportant,
						"is_draft": isDraft,
						"lifetime": lifetime
					},
					headers: {'x-safetext-token': getCookie('token')},
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
							// set the auth cookie
							if (typeof result.token != 'undefined') setCookie('token',result.token,7);
							
							if (isDraft != '1') {
								/** add new chat entry **/
								// generate the new listing content
								var entryContent = Safetext.lastPage.find(".kit-new-message-content");
								entryContent.find(".bubble").html(messageText); // update text
		 				
								// add as a new listing
								Safetext.lastPage.find(".safetext-chat-container").append(entryContent.html());
								
								// increment sent counter
								var sentCount = parseInt(Safetext.lastPage.find(".safetext-sent-counter").html());
								sentCount++;
								$(".safetext-sent-counter").text(sentCount);
							} else {
								// if this is a draft, increment draft counter
								var draftCount = parseInt(Safetext.lastPage.find(".safetext-drafts-counter").html());
								draftCount++;
								$(".safetext-drafts-counter").text(draftCount);
							}
							
							// scroll to bottom of page
							window.scrollTo(0,document.body.scrollHeight);
							
						} else {
							alert('Unable to send. The server said: ' + result.data.message); 
						}
					},
					error: function (request,error) {
						// This callback function will trigger on unsuccessful action                
						alert('Network error has occurred; please try again.');
					}
				});
				
				
				
	
				messageWindow.val(""); // clear message window for next message
			}
		}
		
		return false;
	});


	/**
	 * View Message Settings.
	 * This event will run when the new message settings button is clicked
	 * Opens the settings popup.
	 */
	$(document).on('click', '.safetext-conversation .safetext-message-settings-button', function() {	
		// open the picker in a pop-up
		Safetext.lastPage.find(".safetext-message-settings-popup").popup( "open");
	
		return false;
	});
	
	
	/**
	 * Apply Message Settings.
	 * This event will run when the new message settings Apply button is clicked
	 * Applies setting changes.
	 */
	$(document).on('click', '.safetext-conversation .safetext-message-settings-apply-button', function() {	
		// get form values
		var isDraft = '0';
		var isImportant = '0';
		var thisForm = Safetext.lastPage.find(".safetext-message-settings-form");
		if (thisForm.find('input[name="isdraft"]').is(":checked")) isDraft = '1';
		if (thisForm.find('input[name="isimportant"]').is(":checked")) isImportant = '1';
		var lifetime = thisForm.find('input[name="lifetime"]').val();
	
		if (parseInt(lifetime) < 1) lifetime = '1';
		if (parseInt(lifetime) <= 24) {
			thisForm.find('input[name="lifetime"]').val(lifetime);
			
			// set the attributes
			var messageWindow = Safetext.lastPage.find(".safetext-new-message");
			messageWindow.attr('data-safetext-isdraft', isDraft);
			messageWindow.attr('data-safetext-isimportant', isImportant);
			messageWindow.attr('data-safetext-lifetime', lifetime);
	
			// close the settings menu popup
			Safetext.lastPage.find(".safetext-message-settings-popup").popup( "close");
		} else {
			alert('Message time to expire cannot exceed 24 hours');
			thisForm.find('input[name="lifetime"]').val("24");
		}
	
		return false;
	});
	
	
	/**
	 * Save Contact.
	 * This event will run when a save contact button is clicked.
	 * Updates the on-server contact record with details from the current contact form.
	 */
	$(document).on('click', '.safetext-save-contact-button', function() {	
		// open the picker in a pop-up
		var thisForm = Safetext.lastPage.find(".safetext-edit-contact-form");
		
		if (thisForm.find('input[name="name"]').val() != '') {
			$.ajax({url: '/api/contact',
				data: thisForm.serialize(),
				headers: {'x-safetext-token': getCookie('token')},
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
						// set the auth cookie
						if (typeof result.token != 'undefined') setCookie('token',result.token,7);
						
						// go back to contacts page
						$.mobile.pageContainer.pagecontainer("change", '/webclient/contacts/',{
							reloadPage : true
						});
						
					} else {
						alert('Unable to save changes. The server said: ' + result.data.message); 
					}
				},
				error: function (request,error) {
					// This callback function will trigger on unsuccessful action                
					alert('Network error has occurred; please try again.');
				}
			}); 


		
		} else {
			alert('Enter a name for this contact');
		}
	
		return false;
	});
	
	
	/**
	 * Remove Contact.
	 * This event will run when a contact record's Remove Contact button is clicked.
	 * Removes the on-server contact record for this user.
	 */
	$(document).on('click', '.safetext-contact .safetext-remove-contact-button', function() {	
		// get the contact ID
		var thisButton = Safetext.lastPage.find(".safetext-remove-contact-button");
		var contact = thisButton.attr('data-safetext-contact');

		// send remove request to server
		$.ajax({url: '/api/contact/contact/' + contact,
			headers: {'x-safetext-token': getCookie('token')},
			type: 'delete',               
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
					// set the auth cookie
					if (typeof result.token != 'undefined') setCookie('token',result.token,7);
					
					// go back to contacts page
					$.mobile.pageContainer.pagecontainer("change", '/webclient/contacts/',{
						reloadPage : true
					});
					
					return true;
				} else {
					alert('Unable to remove contact. The server said: ' + result.data.message); 
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
	 * Add Contact.
	 * This event will run when a search result for a new contact is clicked.
	 * Adds the search result as a new contact.
	 */
	$(document).on('click', '.safetext-contactadd .safetext-add-contact-record', function(event) {	

		var entry = $(event.target).closest("li");
		var name = entry.attr("data-safetext-name");
		var id = entry.attr("data-safetext-id");
		var phone = entry.attr("data-safetext-phone");
		var email = entry.attr("data-safetext-email");
		
		if (parseInt(id) > 0) {
			$.ajax({url: '/api/contact',
				data: {"contact": id, "name": name, "phone": phone, "email": email, "whitelist": '0', "blocked": '0'},
				headers: {'x-safetext-token': getCookie('token')},
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
						// set the auth cookie
						if (typeof result.token != 'undefined') setCookie('token',result.token,7);
						
						// go back to contacts page
						$.mobile.pageContainer.pagecontainer("change", '/webclient/contactadd',{
							reloadPage : true,
							allowSamePageTransition: true
						});
						
					} else {
						alert('Unable to add contact. The server said: ' + result.data.message); 
					}
				},
				error: function (request,error) {
					// This callback function will trigger on unsuccessful action                
					alert('Network error has occurred; please try again.');
				}
			}); 


		
		} else {
			alert('Bad Contact Id');
		}
	
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