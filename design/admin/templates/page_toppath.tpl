{let name=Path
     use_urlalias=ezini('URLTranslator','Translation')|eq('enabled')}

    <p class="path">
    {section loop=$module_result.path}
        {section show=$:item.url}
            <a class="path" href={cond( and( $:use_urlalias, is_set( $:item.url_alias ) ), $:item.url_alias,
                                        $:item.url )|ezurl}>{$:item.text|shorten( 18 )|wash}</a>
        {section-else}
            {$:item.text|wash}
        {/section}

        {delimiter}
            <span class="slash">/</span>
        {/delimiter}
    {/section}
    &nbsp;</p>
{/let}
