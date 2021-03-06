

		<!-- GiftFlow module -->
		<div class='center'>
			<div id="welcome_three">
				<div class="welcome_block" id="gifts" >
					<img src="<?php echo base_url();?>assets/images/categories/1.png" style="width:150px;"/>
					<p>What do you want to give? A helping hand? An expert opinion? Handmade jewerly? It's all up to you.</p>
					<p><a href="<?php echo site_url("you/add_good/?type=gift");?>"  class="button btn">Add a Gift</a></p>
				</div>
				
				<div class="welcome_block" id="needs">
					<img src="<?php echo base_url();?>assets/images/categories/16.png" style="width:150px;"/>
				 <p>What do you need? Help in your garden? A pair of gloves? Don't be afraid to ask.</p>
				 <p><a href="<?php echo site_url("you/add_good/?type=need");?>"  class="button btn">Add a Need</a></p>
				</div>
					
				<div class="welcome_block" id="people">
					<img src="<?php echo base_url();?>assets/images/user.png" style="width:150px;"/>
					<p>Who is using GiftFlow near you? Invite your friends and build a network of giving in your community.</p>
					<p><a href="<?php echo site_url('account'); ?>" class="button btn">Complete your Profile</a></p>
				</div>
				
			</div><!-- close welcome_three-->
		
		
			<div id="hyperlocal">
				<h3>Location</h3>
					<p>GiftFlow is a hyperlocal web-community. Our intention is to get you offline and meeting other members as soon as possible.</p>
				<div id="add_location"/>
					<p>Place yourself in our community by entering your zip code below.</p>
						<form method="post" id="location_form" action="<?php echo site_url('account/locations/add');?>" name="enter location">
							<input type="text" name="location"/>
							<input type="submit" value="Add Location" class="button btn"/>
						</form>
				</div><!-- close add_location-->
				<div id="location_added" style="display:none;">
					<p>Great! Now your search results should be much more accurate</p>
				</div>
			</div><!-- close hyperlocal -->	
			
	</div>


<script type="text/javascript">

$(function(){

	var ul = $('#results');
	
	var base_url = '<?php echo site_url(); ?>';
	
	$('.welcome_block').click(function() {
		window.location = (base_url.concat($(this).attr("id")));
   });
	
	$(document).ready(function() { 
            // bind 'myForm' and provide a simple callback function 
            $('#location_form').ajaxForm(function() { 
                $('#add_location').hide();
                $('#location_added').show();
            }); 
            $('#do_not_show').ajaxForm(function() {
            	$('#do_not_show').hide();
            });
        }); 
	
	$("ul.gray_toolbar li a").click(function(){
		$("ul.gray_toolbar li a").removeClass("active");
		$(this).addClass('active');
		ul.empty();
		$.post("<?php echo site_url('ajax/nearby_flow'); ?>", { type: $(this).attr('id'), limit: 5 }, function(data){
			$.each(data.results, function( key, val){
				ul.append($(val.html));
			});
		}, 'json');
	});
	
});
	
	
	


</script>
