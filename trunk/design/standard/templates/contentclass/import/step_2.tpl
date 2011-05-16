{def $has_actions=false()}
<div class="message-warning">

<h2>Here's what will happen when you click the Import button...</h2>

{foreach $log as $class}
	
	{if $class.has_actions}
	{set $has_actions=true()}
	<h3>{$class.head.name} ({if $class.head.exists}Already exists{else}New{/if})</h3>
	
	<table class="list">
		<tr>
			<th>Attribute name</th>
			<th>Exists in installation</th>
			<th>Exists in spec</th>
			<th>Actions</th>
		</tr>
		{foreach $class.attribute_list as $attribute}
		{if $attribute.actions|count|ge(1)}
		<tr>
			<td>{$attribute.name}</td>
			<td>{if $attribute.in_installation}Yes{else}No{/if}</td>
			<td>{if $attribute.in_spec}Yes{else}No{/if}</td>
			<td>{if $attribute.actions|count|ge(1)}
			{foreach $attribute.actions as $action}
				{$action}<br />
			{/foreach}
			{/if}</td>
		</tr>
		{/if}
		{/foreach}
	</table>
	
	{/if}
	
{/foreach}

</div>

{if $has_actions}

<form action="" method="post">
	<textarea name="data" style="display:none;">{$data}</textarea>
	<input type="submit" name="Import" class="defaultbutton" value="Import" />
</form>

{else}

<p>The class specification calls for no actions.</p>

{/if}