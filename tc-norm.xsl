<?xml version="1.0" encoding="UTF-8"?>
<xsl:transform xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version="1.1" xmlns="http://www.tei-c.org/ns/1.0" xmlns:tei="http://www.tei-c.org/ns/1.0" exclude-result-prefixes="tei">
  <xsl:output method="xml" encoding="UTF-8" indent="yes"/>
  <xsl:template match="node()|@*">
    <xsl:copy>
      <xsl:apply-templates select="@*|node()"/>
    </xsl:copy>
  </xsl:template>
  <!-- vers numérotation OK -->
  <xsl:template match="tei:l">
    <xsl:copy>
      <xsl:copy-of select="@*"/>
      <xsl:apply-templates/>
      <xsl:call-template name="copynote"/>
    </xsl:copy>
  </xsl:template>
  <!-- Réinsertion des notes en fin de vers suivants -->
  <xsl:template match="tei:sp/tei:note"/>
  <xsl:template match="node()" mode="copynote"/>
  <xsl:template name="copynote" mode="copynote" match="tei:note">
    <xsl:if test="local-name(preceding-sibling::*[1]) = 'note'">
      <xsl:for-each select="preceding-sibling::*[1]">
        <xsl:apply-templates select="." mode="copynote"/>
      </xsl:for-each>
    </xsl:if>
    <xsl:if test="self::tei:note">
      <xsl:text> </xsl:text>
      <xsl:copy>
        <xsl:copy-of select="@*"/>
        <xsl:apply-templates/>
      </xsl:copy>
    </xsl:if>
  </xsl:template>
</xsl:transform>
