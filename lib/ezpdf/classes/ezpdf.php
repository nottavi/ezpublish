<?php
//
// Created on: <26-Aug-2003 15:15:32 kk>
//
// Copyright (C) 1999-2003 eZ systems as. All rights reserved.
//
// This source file is part of the eZ publish (tm) Open Source Content
// Management System.
//
// This file may be distributed and/or modified under the terms of the
// "GNU General Public License" version 2 as published by the Free
// Software Foundation and appearing in the file LICENSE.GPL included in
// the packaging of this file.
//
// Licencees holding valid "eZ publish professional licences" may use this
// file in accordance with the "eZ publish professional licence" Agreement
// provided with the Software.
//
// This file is provided AS IS with NO WARRANTY OF ANY KIND, INCLUDING
// THE WARRANTY OF DESIGN, MERCHANTABILITY AND FITNESS FOR A PARTICULAR
// PURPOSE.
//
// The "eZ publish professional licence" is available at
// http://ez.no/products/licences/professional/. For pricing of this licence
// please contact us via e-mail to licence@ez.no. Further contact
// information is available at http://ez.no/home/contact/.
//
// The "GNU General Public License" (GPL) is available at
// http://www.gnu.org/copyleft/gpl.html.
//
// Contact licence@ez.no if any conditions of this licencing isn't clear to
// you.
//

/*! \file eztemplateautoload.php
*/

include_once( 'lib/ezpdf/classes/class.ezpdftable.php' );
include_once( 'lib/ezpdf/classes/class.pdf.php' );

include_once( 'lib/ezfile/classes/ezfile.php' );
include_once( 'lib/ezutils/classes/eztexttool.php' );

/*!
  \class eZPDF ezpdf.php
  \brief The class eZPDF does

*/

class eZPDF
{

    /*!
     Initializes the object with the name $name, default is "attribute".
    */
    function eZPDF( $name = "pdf" )
    {
        $this->Operators = array( $name );
    }

    /*!
     Returns the template operators.
    */
    function &operatorList()
    {
        return $this->Operators;
    }

    /*!
     See eZTemplateOperator::namedParameterList()
    */
    function namedParameterList()
    {
        return array( 'operation' => array( 'type' => 'string',
                                            'required' => true,
                                            'default' => '' ) );
    }

    /*!
     Display the variable.
    */
    function modify( &$tpl, &$operatorName, &$operatorParameters, &$rootNamespace, &$currentNamespace, &$operatorValue, &$namedParameters )
    {
        if ( ! isset( $this->PDF ) )
        {
            $this->createPDF();
        }

        switch ( $namedParameters['operation'] )
        {
            case 'toc':
            {
                $specification = $tpl->elementValue( $operatorParameters[1], $rootNamespace, $currentNamespace );

                $operatorValue = '<C:callTOC>';
                eZDebug::writeNotice( 'PDF: Generating TOC', 'eZPDF::modify' );
            } break;

            case 'setfont':
            {
                $font = $tpl->elementValue( $operatorParameters[1], $rootNamespace, $currentNamespace );

                $operatorValue = '<ezCall:callFont';

                if ( isset( $font['name'] ) )
                {
                    $operatorValue .= ':name:'. $font['name'];
                }
                if ( isset( $font['size'] ) )
                {
                    $operatorValue .= ':size:'. $font['size'];
                }

                $operatorValue .= '>';

                eZDebug::writeNotice( 'PDF: Changed font, name: '. $font['name'] .', size: '. $font['size'], 'eZPDF::modify' );
            } break;

            case 'table': //TODO
            {
                $table = $tpl->elementValue( $operatorParameters[1], $rootNamespace, $currentNamespace );

                $this->PDF->ezTable( $table['data'], '', '', $table['options'] );
            } break;

            case 'header':
            {
                $header = $tpl->elementValue( $operatorParameters[1], $rootNamespace, $currentNamespace );

                if ( !isset( $header['align'] ) )
                    $header['align'] = 'left';

                $operatorValue = '<ezCall:callHeader:level:'. $header['level'] .':size:'. $header['size'] .':justification:'. $header['align'] .':label:'.
                     rawurlencode( $header['text'] ) .'>'. $header['text'] .'</ezCall:callHeader><C:callSpace><C:callNewLine>';

                eZDebug::writeNotice( 'PDF: Added header: '. $header['text'] .', size: '. $header['size'] .
                                      ', align: '. $header['align'] .', level: '. $header['level'],
                                      'eZPDF::modify' );
            } break;

            case 'create':
            {
                $this->createPDF();
            } break;

            case 'newline':
            {
                $operatorValue = '<C:callNewLine>';
            } break;

            case 'newpage':
            {
                $operatorValue = '<C:callNewPage>';

                eZDebug::writeNotice( 'PDF: New page', 'eZPDF::modify' );
            } break;

            case 'image': //TODO
            {
                $image = $tpl->elementValue( $operatorParameters[1], $rootNamespace, $currentNamespace );

                $width = isset( $image['width'] ) ? $image['width']: 100;
                $height = isset( $image['height'] ) ? $image['height']: 100;

                $this->PDF->addJpegFromFile( $image['src'], 0, $this->PDF->offsetY()-$height, $width, $height );
                eZDebug::writeNotice( 'PDF: Added Image '.$image['src'].' to PDF file', 'eZPDF::modify' );
            } break;

            case 'anchor':
            {
                $name = $tpl->elementValue( $operatorParameters[1], $rootNamespace, $currentNamespace );

                $operatorValue = '<C:callAnchor:'. $name['name'] .':FitH:>';
                eZDebug::writeNotice( 'PDF: Added anchor: '.$name['name'], 'eZPDF::modify' );
            } break;

            case 'link': // external link
            {
                $link = $tpl->elementValue( $operatorParameters[1], $rootNamespace, $currentNamespace );

                $operatorValue = '<c:alink:'. $link['url'] .'>'. $link['text'] .'</c:alink>';
                eZDebug::writeNotice( 'PDF: Added link, url: '.$link['url'], 'eZPDF::modify' );
            } break;

            case 'stream':
            {
                $this->PDF->ezStream();
            }

            case 'close':
            {
                include_once( 'lib/ezutils/classes/eztexttool.php' );
                $filename = 'tmp.pdf';
                eZFile::create( $filename, eZSys::storageDirectory() .'/pdf', $this->PDF->ezOutput() );
                eZDebug::writeNotice( 'PDF file closed and saved to '. eZSys::storageDirectory() .'/pdf/'. $filename, 'eZPDF::modify' );
            } break;

            case 'strike':
            {
                $text = $tpl->elementValue( $operatorParameters[1], $rootNamespace, $currentNamespace );
                $operatorValue = '<c:strike>'. $text .'</c:strike>';
                eZDebug::writeNotice( 'Striked text added to PDF: "'. $text .'"', 'eZPDF::modify' );
            } break;

            /* usage : execute/add text to pdf file, pdf(execute,<text>) */
            case 'execute':
            {
                $text = $tpl->elementValue( $operatorParameters[1], $rootNamespace, $currentNamespace );

                $text = str_replace( array( ' ', "\n", "\t" ), '', $text );

                $this->PDF->ezText( $text );
                eZDebug::writeNotice( 'Execute text in PDF: "'. $text .'"', 'eZPDF::modify' );
            } break;

            /* usage : pdf(text, <text>, array( 'font' => <fontname>, 'size' => <fontsize> )) */
            case 'text':
            {
                $text = $tpl->elementValue( $operatorParameters[1], $rootNamespace, $currentNamespace );

                $operatorValue = '';
                $changeFont = false;

                if ( count( $operatorParameters ) >= 3)
                {
                    $textSettings = $tpl->elementValue( $operatorParameters[2], $rootNamespace, $currentNamespace );

                    if ( isset( $textSettings ) )
                    {
                        $operatorValue .= '<ezCall:callText';
                        $changeFont = true;

                        if ( isset( $textSettings['font'] ) )
                        {
                            $operatorValue .= ':font:'. $textSettings['font'];
                        }

                        if ( isset( $textSettings['size'] ) )
                        {
                            $operatorValue .= ':size:'. $textSettings['size'];
                        }

                        if ( isset( $textSettings['align'] ) )
                        {
                            $operatorValue .= ':justification:'. $textSettings['align'];
                        }

                        $operatorValue .= '>';
                    }
                }

                $operatorValue .= $text;
                if ( $changeFont )
                {
                    $operatorValue .= '</ezCall:callText>';
                }

                eZDebug::writeNotice( 'Text added to PDF: "'. $text .'"', 'eZPDF::modify' );
            } break;

            default:
            {
                eZDebug::writeNotice( 'No operation defined, nothing done', 'eZPDF::modify' );
            }

        }

    }

    /*
     \private
     Create PDF object
    */
    function createPDF()
    {
        $this->PDF = new eZPDFTable();
        $this->PDF->selectFont( 'lib/ezpdf/classes/fonts/Helvetica' );
        eZDebug::writeNotice( 'PDF: File created' );
    }

    /// The array of operators, used for registering operators
    var $Operators;
    var $PDF;

    var $bufferedText = '';
}


?>
