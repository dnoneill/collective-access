<?php
/* ----------------------------------------------------------------------
 * themes/default/views/Search/ca_objects_search_subview_html.php : 
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2013-2015 Whirl-i-Gig
 *
 * For more information visit http://www.CollectiveAccess.org
 *
 * This program is free software; you may redistribute it and/or modify it under
 * the terms of the provided license as published by Whirl-i-Gig
 *
 * CollectiveAccess is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTIES whatsoever, including any implied warranty of 
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
 *
 * This source code is free and modifiable under the terms of 
 * GNU General Public License. (http://www.gnu.org/copyleft/gpl.html). See
 * the "license.txt" file for details, or visit the CollectiveAccess web site at
 * http://www.CollectiveAccess.org
 *
 * ----------------------------------------------------------------------
 */
 
	$qr_results 		= $this->getVar('result');
	$va_block_info 		= $this->getVar('blockInfo');
	$vs_block 			= $this->getVar('block');
	$vn_start		 	= (int)$this->getVar('start');			// offset to seek to before outputting results
	$vn_hits_per_block 	= (int)$this->getVar('itemsPerPage');
	$vb_has_more 		= (bool)$this->getVar('hasMore');
	$vs_search 			= (string)$this->getVar('search');
	$vn_init_with_start	= (int)$this->getVar('initializeWithStart');
	$va_access_values = caGetUserAccessValues($this->request);
	$o_config = caGetSearchConfig();
	$o_browse_config = caGetBrowseConfig();
	$va_browse_types = array_keys($o_browse_config->get("browseTypes"));
	$o_icons_conf = caGetIconsConfig();
	$va_object_type_specific_icons = $o_icons_conf->getAssoc("placeholders");
	if(!($vs_default_placeholder = $o_icons_conf->get("placeholder_media_icon"))){
		$vs_default_placeholder = "<i class='fa fa-picture-o fa-2x'></i>";
	}
	$vs_default_placeholder_tag = "<div class='multisearchImgPlaceholder'>".$vs_default_placeholder."</div>";


	if ($qr_results->numHits() > 0) {
		if (!$this->request->isAjax()) {
?>
			<small class="pull-right">
<?php
				if(in_array($vs_block, $va_browse_types)){
?>
				<span class='multisearchFullResults'><?php print caNavLink($this->request, _t('View All').'&nbsp;&nbsp;<span class="icon-arrow-up-right"></span>', '', '', 'Search', '{{{block}}}', array('search' => $vs_search)); ?></span> 
<?php
				}
?>
			</small>
			<H3><?php print $va_block_info['displayName']."&nbsp;&nbsp;<span class='highlight'>(".$qr_results->numHits()." Results)</span>"; ?></H3>
			<div id='browseResultsContainer' >
<?php
		}
		$vn_count = 0;
		$t_list_item = new ca_list_items();
		
		while($qr_results->nextHit()) {
?>
		<div class="col-sm-3">
			<div class='{{{block}}}Result multisearchResult'>
<?php 
				$vs_image = $qr_results->get('ca_object_representations.media.widepreview', array("checkAccess" => $va_access_values));
				if(!$vs_image){
					$t_list_item->load($qr_results->get("type_id"));
					$vs_typecode = $t_list_item->get("idno");
					if($vs_type_placeholder = caGetPlaceholder($vs_typecode, "placeholder_media_icon")){
						$vs_image = "<div class='multisearchImgPlaceholder'>".$vs_type_placeholder."</div>";
					}else{
						$vs_image = $vs_default_placeholder_tag;
					}
				}
				print $qr_results->getWithTemplate('<l>'.$vs_image.'</l>', array("checkAccess" => $va_access_values));

				print "<p>".$qr_results->get('ca_objects.preferred_labels.name', array('returnAsLink' => true))."</p>";
				if ($qr_results->get('ca_objects.idno')) {
					print "<p>ID: ".$qr_results->get('ca_objects.idno')."</p>";
				}
				if ($qr_results->get('ca_objects.date')) {
					print "<p>".$qr_results->get('ca_objects.date')."</p>";
				}
			print "</div><!-- end blockResult -->";
?>	
			</div>
<?php	
			$vn_count++;
			if ($vn_count == $vn_hits_per_block) {break;}
		}
		print caNavLink($this->request, _t('Next %1', $vn_hits_per_block), 'jscroll-next', '*', '*', '*', array('s' => $vn_start + $vn_hits_per_block, 'key' => $this->getVar("cacheKey"), 'block' => $vs_block, 'search'=> $vs_search));
		
		if (!$this->request->isAjax()) {
?>
					</div><!-- end browseResultsContainer -->
<script type="text/javascript">
	jQuery(document).ready(function() {
		jQuery('#browseResultsContainer').jscroll({
			autoTrigger: true,
			loadingHtml: "<?php print caBusyIndicatorIcon($this->request).' '.addslashes(_t('Loading...')); ?>",
			padding: 60,
			nextSelector: 'a.jscroll-next'
		});
	});

</script><?php
		}
	}
	
	TooltipManager::add('#caObjectsFullResults', 'Click here for full results');
?>