jQuery(document).ready(function($){$("#_llms_trigger_type").change(function(){var e=$("option:selected",this),t=this.value;if("lesson_completed"==t)var n=get_all_lessons();else if("section_completed"==t)var a=get_all_sections();else if("course_completed"==t)var l=get_all_courses();else if("course_purchased"==t)var l=get_all_courses();else clear_trigger_select()}),$("#_llms_engagement_type").change(function(){var e=$("option:selected",this),t=this.value;if("email"==t)var n=get_all_emails();if("achievement"==t)var a=get_all_achievements();if("certificate"==t)var l=get_all_certificates();else clear_engagement_select()})}),return_data=function(e){clear_trigger_select();var t=document.createElement("th"),n=document.createElement("Label");n.setAttribute("for","trigger-select"),n.innerHTML="Engagement Trigger",t.appendChild(n);var a=document.createElement("td"),l=document.createElement("select");if(l.setAttribute("id","trigger-select"),l.setAttribute("class","chosen-select chosen select section-select"),l.setAttribute("name","_llms_engagement_trigger"),a.appendChild(l),!jQuery("#trigger-select").length){jQuery(l).append('<option value="" selected disabled>Please select a post...</option>');for(var r in e)if(e.hasOwnProperty(r)){var s=jQuery("<option />").val(e[r].ID).text(e[r].post_title);jQuery(l).append(s)}jQuery(".engagement-option").append(t),jQuery(".engagement-option").append(a)}},return_engagement_data=function(e){console.log(e),clear_engagement_select();var t=document.createElement("th"),n=document.createElement("Label");n.setAttribute("for","engagement-select"),n.innerHTML="Event",t.appendChild(n);var a=document.createElement("td"),l=document.createElement("select");if(l.setAttribute("id","engagement-select"),l.setAttribute("class","chosen-select chosen select section-select"),l.setAttribute("name","_llms_engagement"),a.appendChild(l),!jQuery("#engagement-select").length){jQuery(l).append('<option value="" selected disabled>Please select an engagement...</option>');for(var r in e)if(e.hasOwnProperty(r)){var s=jQuery("<option />").val(e[r].ID).text(e[r].post_title);jQuery(l).append(s)}jQuery(".engagement-posts").append(t),jQuery(".engagement-posts").append(a)}},clear_trigger_select=function(){jQuery(".engagement-option").empty()},clear_engagement_select=function(){jQuery(".engagement-posts").empty()};