(function () {
	'use strict';
	var root = document.querySelector('[data-system-email-composer]');
	if (!root) return;
	var i18n = (window.CONTACTPANDA_SYSTEM_EMAILS || {}).i18n || {};
	var headerInput = document.querySelector('[data-system-email-header]');
	var footerInput = document.querySelector('[data-system-email-footer]');
	function parse(value) { try { var data = JSON.parse(value || '[]'); return Array.isArray(data) ? data : []; } catch (ignore) { return []; } }
	var sections = {header: parse(root.dataset.header), footer: parse(root.dataset.footer)};
	function sync() { headerInput.value = JSON.stringify(sections.header); footerInput.value = JSON.stringify(sections.footer); }
	function icon(name, title) { var button = document.createElement('button'); button.type = 'button'; button.className = 'button contactpanda-icon-button'; button.title = title; button.innerHTML = '<span class="dashicons dashicons-' + name + '"></span>'; return button; }
	function chooseImage(block) { if (!window.wp || !wp.media) return; var frame = wp.media({title:i18n.image,button:{text:i18n.image},multiple:false}); frame.on('select',function(){var item=frame.state().get('selection').first().toJSON();block.url=item.url||'';sync();render();});frame.open(); }
	function blockRow(section, block, index) {
		var row=document.createElement('div');row.className='contactpanda-system-email-block';var label=document.createElement('strong');label.textContent=block.type==='image'?i18n.image:(block.type==='separator'?i18n.separator:i18n.text);row.appendChild(label);
		if(block.type==='image'){var image=document.createElement('button');image.type='button';image.className='contactpanda-system-email-image';image.innerHTML=block.url?'<img src="'+block.url.replace(/"/g,'&quot;')+'" alt="">':'<span class="dashicons dashicons-format-image"></span>';image.addEventListener('click',function(){chooseImage(block);});row.appendChild(image);}else if(block.type==='text'){var area=document.createElement('textarea');area.rows=3;area.value=block.content||'';area.addEventListener('input',function(){block.content=area.value;sync();});row.appendChild(area);}else{row.appendChild(document.createElement('hr'));}
		var actions=document.createElement('span');actions.className='contactpanda-system-email-block-actions';var up=icon('arrow-up-alt2',i18n.moveUp),down=icon('arrow-down-alt2',i18n.moveDown),remove=icon('trash',i18n.remove);up.disabled=index===0;down.disabled=index===sections[section].length-1;up.addEventListener('click',function(){sections[section].splice(index-1,0,sections[section].splice(index,1)[0]);sync();render();});down.addEventListener('click',function(){sections[section].splice(index+1,0,sections[section].splice(index,1)[0]);sync();render();});remove.addEventListener('click',function(){sections[section].splice(index,1);sync();render();});actions.append(up,down,remove);row.appendChild(actions);return row;
	}
	function section(name){var box=document.createElement('section');box.className='contactpanda-system-email-composer-section';var heading=document.createElement('h3');heading.textContent=name==='header'?i18n.header:i18n.footer;box.appendChild(heading);sections[name].forEach(function(block,index){box.appendChild(blockRow(name,block,index));});var add=document.createElement('div');add.className='contactpanda-system-email-add';['image','text','separator'].forEach(function(type){var button=document.createElement('button');button.type='button';button.className='button';button.textContent='+ '+(type==='image'?i18n.image:(type==='text'?i18n.text:i18n.separator));button.addEventListener('click',function(){sections[name].push({type:type,url:'',content:''});sync();render();});add.appendChild(button);});box.appendChild(add);return box;}
	function render(){root.replaceChildren(section('header'),section('footer'));}
	sync();render();
}());
