var map;
$(function(){
    if(!isMobile()){
        $("#contactForm").stick_in_parent({offset_top:120});
        $("#sideBar").stick_in_parent({offset_top:150});
    }

    var hash = window.location.hash.substr(1);
    if(hash == "conversion"){
        fbq('track', 'Lead',{content_category:"contact",content_name:"fromConversion"});
        ga('send', 'event', 'contact', 'conversion', "visitor gone to contact from conversion");
    }

    $("a.cv").on("click",function(){
       $("input[name='cv']").click();
    });
    $("input[name='cv']").on("change",function(){
        $("a.cv").addClass("selected");
    });
    $('#careerForm').ajaxForm({
        uploadProgress: function(event,position,total,percentComplete){

        },
        beforeSubmit:function(formData, jqForm, options){
            loading(true);
        },
        success:function(responseText, statusText, xhr, $form){
            loading(false);
            if(responseText.status){
                //fbq('track', 'Lead',{content_category:"form",content_name:"contact-form-sended"});
                //ga('send', 'event', 'form', 'submit', 'Contact form sended');
                $("input:not([type='submit'])").val("");
                $.confirm({
                    title:false,
                    content:"Size en yakın sürede dönüş yapacağız.",
                    columnClass: 'columns small-10 medium-4 large-4',
                    buttons: {
                        cancel: {
                            text: $LANG["OK"],
                            btnClass: 'btn-primary',
                            keys: ['esc'],
                            action: function(){

                            }
                        }
                    }
                });
            } else {
                $.confirm({
                    title:false,
                    content:"Lütfen tüm alanları doldurunuz.",
                    columnClass: 'columns small-10 medium-4 large-4',
                    buttons: {
                        cancel: {
                            text: $LANG["OK"],
                            btnClass: 'btn-primary',
                            keys: ['esc'],
                            action: function(){

                            }
                        }
                    }
                });
            }
        }
        //timeout:   3000
    });
    $("#careerForm .button").on("click",function(){
        $(this).parents("form").submit();
    });

    $('.map').each(function (index, Element) {
        var coords = (typeof $(this).attr("data-latlng") != "undefined"?$(this).attr("data-latlng").split(","):"");


        var latlng = new google.maps.LatLng(parseFloat(coords[0]), parseFloat(coords[1]));
        var myOptions = {
            zoom: 16,
            center: latlng,
            mapTypeId: google.maps.MapTypeId.ROADMAP,
            disableDefaultUI: true,
            mapTypeControl: false,
            zoomControl: true,
            zoomControlOptions: {
                style: google.maps.ZoomControlStyle.SMALL
            }
        };
        var map = new google.maps.Map(Element, myOptions);

        // if($(this).index == 0){
        //   map.addListener('tilesloaded', function (){
        //      alert('Map loaded!');
        //   });
        // }

        //var image = new google.maps.MarkerImage("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAEYAAABGCAYAAABxLuKEAAAACXBIWXMAAAsTAAALEwEAmpwYAAAH12lUWHRYTUw6Y29tLmFkb2JlLnhtcAAAAAAAPD94cGFja2V0IGJlZ2luPSLvu78iIGlkPSJXNU0wTXBDZWhpSHpyZVN6TlRjemtjOWQiPz4gPHg6eG1wbWV0YSB4bWxuczp4PSJhZG9iZTpuczptZXRhLyIgeDp4bXB0az0iQWRvYmUgWE1QIENvcmUgNS42LWMxNDAgNzkuMTYwNDUxLCAyMDE3LzA1LzA2LTAxOjA4OjIxICAgICAgICAiPiA8cmRmOlJERiB4bWxuczpyZGY9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkvMDIvMjItcmRmLXN5bnRheC1ucyMiPiA8cmRmOkRlc2NyaXB0aW9uIHJkZjphYm91dD0iIiB4bWxuczp4bXA9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC8iIHhtbG5zOmRjPSJodHRwOi8vcHVybC5vcmcvZGMvZWxlbWVudHMvMS4xLyIgeG1sbnM6cGhvdG9zaG9wPSJodHRwOi8vbnMuYWRvYmUuY29tL3Bob3Rvc2hvcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RFdnQ9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZUV2ZW50IyIgeG1sbnM6dGlmZj0iaHR0cDovL25zLmFkb2JlLmNvbS90aWZmLzEuMC8iIHhtbG5zOmV4aWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20vZXhpZi8xLjAvIiB4bXA6Q3JlYXRvclRvb2w9IkFkb2JlIFBob3Rvc2hvcCBDQyAyMDE3IChXaW5kb3dzKSIgeG1wOkNyZWF0ZURhdGU9IjIwMTgtMDUtMzBUMjI6Mzg6MDYrMDM6MDAiIHhtcDpNb2RpZnlEYXRlPSIyMDE5LTAyLTE3VDIzOjE5OjE4KzAzOjAwIiB4bXA6TWV0YWRhdGFEYXRlPSIyMDE5LTAyLTE3VDIzOjE5OjE4KzAzOjAwIiBkYzpmb3JtYXQ9ImltYWdlL3BuZyIgcGhvdG9zaG9wOkNvbG9yTW9kZT0iMyIgcGhvdG9zaG9wOklDQ1Byb2ZpbGU9InNSR0IgSUVDNjE5NjYtMi4xIiB4bXBNTTpJbnN0YW5jZUlEPSJ4bXAuaWlkOmJkNWEzM2VlLWMzODAtNGNiNC04NzIxLTU0NTdhOGI2YTcxZSIgeG1wTU06RG9jdW1lbnRJRD0iYWRvYmU6ZG9jaWQ6cGhvdG9zaG9wOmE3ZmM5YTI0LTBhMzMtMDI0OS05NjI4LWM2OWJmMmIxZjY2NiIgeG1wTU06T3JpZ2luYWxEb2N1bWVudElEPSJ4bXAuZGlkOmY3OGNjYzEzLTJmNjktYWE0NC04MGYxLTQ0ZDlhMTE4Y2NlYiIgdGlmZjpPcmllbnRhdGlvbj0iMSIgdGlmZjpYUmVzb2x1dGlvbj0iNzIwMDAwLzEwMDAwIiB0aWZmOllSZXNvbHV0aW9uPSI3MjAwMDAvMTAwMDAiIHRpZmY6UmVzb2x1dGlvblVuaXQ9IjIiIGV4aWY6Q29sb3JTcGFjZT0iNjU1MzUiIGV4aWY6UGl4ZWxYRGltZW5zaW9uPSIyODAiIGV4aWY6UGl4ZWxZRGltZW5zaW9uPSI3MCI+IDx4bXBNTTpIaXN0b3J5PiA8cmRmOlNlcT4gPHJkZjpsaSBzdEV2dDphY3Rpb249ImNyZWF0ZWQiIHN0RXZ0Omluc3RhbmNlSUQ9InhtcC5paWQ6Zjc4Y2NjMTMtMmY2OS1hYTQ0LTgwZjEtNDRkOWExMThjY2ViIiBzdEV2dDp3aGVuPSIyMDE4LTA1LTMwVDIyOjM4OjA2KzAzOjAwIiBzdEV2dDpzb2Z0d2FyZUFnZW50PSJBZG9iZSBQaG90b3Nob3AgQ0MgMjAxNyAoV2luZG93cykiLz4gPHJkZjpsaSBzdEV2dDphY3Rpb249InNhdmVkIiBzdEV2dDppbnN0YW5jZUlEPSJ4bXAuaWlkOjUyOWVhMmRiLTE4NDEtMjE0OC05MjhhLTgzZGI0NmNmODMxNSIgc3RFdnQ6d2hlbj0iMjAxOC0wNS0zMVQwMDozNjozNCswMzowMCIgc3RFdnQ6c29mdHdhcmVBZ2VudD0iQWRvYmUgUGhvdG9zaG9wIENDIDIwMTcgKFdpbmRvd3MpIiBzdEV2dDpjaGFuZ2VkPSIvIi8+IDxyZGY6bGkgc3RFdnQ6YWN0aW9uPSJzYXZl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", null, null, null, new google.maps.Size(60,60));
        var marker = new google.maps.Marker({ // Set the marker
            position: latlng, // Position marker to coordinates
            //icon:image, //use our image as the marker
            map: map, // assign the market to our map variable
            url: $(this).attr("data-url")
        });

        google.maps.event.addListener(marker, 'click', function() {
            // window.location.href = (this.url, '_blank');
            window.open(this.url, '_blank');
        });

    }).promise().done( function(){ $(window).trigger( 'load' ); });
});