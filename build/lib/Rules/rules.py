import html
import re
import os

class RuleSet:
    def extract_content_sections(self, content, file_name):
        raise NotImplementedError("This method should be implemented by subclasses")

    def post_process_html(self, html_content, dir, file_name):
        return html_content

# Variants
class VariantsRuleSet(RuleSet):
    variant_classes = {
        "cosmic": "cosmic",
        "elemental": "elemental",
        "ethereal": "ethereal",
        "guardian": "guardian",
        "lucky": "lucky",
        "mythical": "mythical",
        "nature": "nature",
        "nightmare": "nightmare",
        "rage": "rage",
        "resource": "resource",
        "summoner": "summoner",
        "unstable": "unstable",
        "utility": "utility"
    }
    
    def extract_content_sections(self, content, file_name):

        def preprocess_newlines(text):
            text = re.sub(r'\n(?!\n|\d|-)', ' ', text)
            return text

        if os.path.basename(file_name) == "index.txt":
            isIndexFile = True
        else:
            isIndexFile = False

        if isIndexFile and "Groups and Variants" in content:
            mainIndexFile = True
            # Preprocess the content first
            content = preprocess_newlines(content.strip())
        else:
            mainIndexFile = False 

        # Discard the beginning of the text file
        content = re.sub(r'^<span style=.*?</span>:', '', content)

        # Split the content into header and rest based on -----
        header_info, *rest = re.split(r'-{4,}', content.strip())
        rest_content = rest[0] if rest else ""

        # In the header, replace single newlines with spaces (to merge lines) and double newlines with a unique separator
        if not mainIndexFile:
            header_info = re.sub(r'\n(?!\n)', ' ', header_info)
            header_info = re.sub(r'\n(?!\n)', '\n\n', header_info)

        # Extract key:value pairs and categorize them
        key_value_pattern = re.compile(r"^(.*?):\s(.*?)$", re.MULTILINE)
        if isIndexFile:
            sections = ["", "", ""] #Initialize with 3 sections
        else:
            sections = ["", "", "", ""]  # Initialize with 4 sections
        section_content = []

        if isIndexFile and len(rest) > 1:
            sections[2] = rest[1]

        in_variant_info = False
        in_other_info = False

        for line in rest_content.splitlines():
            line = line.strip()
            if not line:
                continue
            if key_value_pattern.match(line):
                key, value = key_value_pattern.match(line).groups()
                # Wrap "Taking Damage" and "Dealing Damage" in their respective CSS classes
                if "Taking Damage" in key:
                    key = key.replace("Taking Damage", '<span class="taking-damage">Taking Damage</span>')
                if "Dealing Damage" in key:
                    key = key.replace("Dealing Damage", '<span class="dealing-damage">Dealing Damage</span>')
                section_content.append(f'<span class="key">{key}:</span> <span class="value">{value}</span>')
            else:
                if 'Variant Information:' in line:
                    sections[1] = "\n".join(section_content)  # Finish Section 2
                    section_content = []
                    section_content.append(f'<h2>{line}</h2>')
                    in_variant_info = True
                    in_other_info = False
                elif 'Other Information:' in line:
                    sections[2] = "\n".join(section_content)  # Finish Section 3
                    section_content = []
                    section_content.append(f'<h2>{line}</h2>')
                    in_variant_info = False
                    in_other_info = True
                else:
                    section_content.append(f'{line}')

        sections[0] = header_info.strip()
        if isIndexFile:
            sections[1] = "\n".join(section_content)
        else:
            sections[3] = "\n".join(section_content)  # Finish Section 4

        return sections

    def post_process_html(self, html_content, dir, file_name):
        dir_path, dir_name = os.path.split(dir)
        variant_class = self.variant_classes.get(dir_name.lower(), "default")
        html_content = html_content.replace("VariantClass", variant_class)
        if os.path.basename(file_name) == "index.txt":
            if variant_class == "default":
                html_content = html_content.replace("Index", "Variants")
            else:
                html_content = html_content.replace("Index", variant_class.upper())
            
        return html_content

#Dinos
class DinosRuleSet(RuleSet):

    def extract_content_sections(self, content, file_name):
        if os.path.basename(file_name) == "index.txt":
            isIndexFile = True
        else:
            isIndexFile = False
        # Extract key:value pairs and categorize them
        key_value_pattern = re.compile(r"^(.*?):\s(.*?)$", re.MULTILINE)
        if isIndexFile:
            sections = [""] #Initialize with 1 sections
        else:
            sections = ["", "", "", "", "", "", "", ""]  # Initialize with 8 sections
        section_content = []

        section = 0
        foodSection = False
        for line in content.splitlines():
            line = line.strip()
            if not line:
                #increment section
                sections[section] = "\n".join(section_content)
                section += 1
                section_content = []
                continue
            if key_value_pattern.match(line):
                    key, value = key_value_pattern.match(line).groups()
                    section_content.append(f'<span class="dino-key">{key}:</span> <span class="dino-value">{value}</span>')
            elif isIndexFile:
                    section_content.append(f'{line}')
            else:
                if re.match(r'.*:$', line):
                    section_content.append(f'<h3>{line}</h3>')
                    if "Food Types" in line:
                        foodSection = True
                        section_content.append(f'\n<ul>')
                else:
                    if foodSection is True:
                        section_content.append(f'<li>{line}</li>')

        #finish final section
        if foodSection is True:
            section_content.append(f'</ul>')
            sections[section] = "".join(section_content)
        else:
            sections[section] = "\n".join(section_content)

        return sections

    def post_process_html(self, html_content, dir, file_name):
        html_content = html_content.replace("default", "")
        dir_path, dir_name = os.path.split(dir)
        dir_name = dir_name.split(' ', 1)[1]

        if os.path.basename(file_name) == "index.txt":
            html_content = html_content.replace("Index", dir_name)
        return html_content


# Most lists and text files
class listRuleSet(RuleSet):

    def extract_content_sections(self, content, file_name):

        # Preprocess the content to handle newlines. Combine lines seperated with a single newline
        # unless the next line beings with a digit or a -, indicating that it's a list item.
        def preprocess_newlines(text):
            text = re.sub(r'\n(?!\n|\d|-)', ' ', text)
            return text

        # Preprocess the content first
        preprocessed_content = preprocess_newlines(content.strip())

        # Split the content into header and rest based on -----
        header_info, *rest = re.split(r'-{4,}', preprocessed_content)

        rest_content = rest[0] if rest else ""

        # Extract key:value pairs and categorize them
        key_value_pattern = re.compile(r"^(\b\w+\b\s?){0,3}:\s(.*?)$", re.MULTILINE)
        sections = ["", ""]  # Initialize with 2 sections
        section_content = []

        if not rest_content:
            rest_content = header_info
            oneSection = True
        else:
            oneSection = False

        listStart = False
        bulletListStart = False
        for line in rest_content.splitlines():
            line = line.strip()
            if not line:
                continue
            if key_value_pattern.match(line) and not re.fullmatch(r"\d{2}-\d{2}-\d{2}\.txt", file_name):
                key, value = key_value_pattern.match(line).groups()
                section_content.append(f'<span class="list-key">{key}:</span> <span class="list-value">{value}</span>')
            elif line.startswith('- ') or line.startswith('-'):
                if not bulletListStart:
                    section_content.append('<ul>')
                    bulletListStart = True
                section_content.append(f'<li>{line[1:]}</li>')
            elif re.match(r'^\d+\.\)', line) or re.match(r'^\d+\.', line):
                if bulletListStart:
                    section_content.append('</ul>')
                    bulletListStart = False
                line = re.sub(r'^\d+\.\)?', '', line).strip()
                if not listStart:
                    section_content.append('<ol>')
                    listStart = True
                section_content.append(f'<li>{line}</li>')
            else:
                if listStart:
                    section_content.append('</ol>')
                    listStart = False
                if bulletListStart:
                    section_content.append('</ul>')
                    bulletListStart = False
                section_content.append(f'{line}\n')

        if listStart:  # Close the ordered list if it's still open
            section_content.append('</ol>')
        if bulletListStart:  # Close the unordered list if it's still open
            section_content.append('</ul>')

        if oneSection is False:
            sections[0] = header_info.strip()
            sections[1] = "\n".join(section_content) if section_content else ""
        else:
            sections[0] = "".join(section_content)

        return sections

    def post_process_html(self, html_content, dir, file_name):
        html_content = html_content.replace("default", "")
        dir_path, dir_name = os.path.split(dir)
        dir_name = dir_name.split(' ', 1)[1]

        if os.path.basename(file_name) == "index.txt":
            html_content = html_content.replace("Index", dir_name)
        return html_content

#Bosses
class BossesRuleSet(RuleSet):

    def extract_content_sections(self, content, file_name):

        # Preprocess the content to handle newlines. Combine lines separated with a single newline
        # unless the next line begins with a digit or a -, indicating that it's a list item.
        def preprocess_newlines(text):
            # Replace double (or more) newlines with a placeholder
            text = text.replace('\n\n', '[newline]')
            # Combine single newlines unless the next line begins with a digit or a dash
            text = re.sub(r'\n(?!\n|\d|-)', ' ', text)
            # Restore placeholders to double newlines
            text = text.replace('[newline]', '\n\n')
            return text

        # Preprocess the content first
        preprocessed_content = preprocess_newlines(content.strip())

        # Split the content into header and rest based on -----
        header_info, *rest = re.split(r'-{4,}', preprocessed_content)

        rest_content = rest[0] if rest else ""

        # Extract key:value pairs and categorize them
        key_value_pattern = re.compile(r"^((?:\b\w+\b\s?){1,4}):\s(.*?)$", re.MULTILINE)
        sections = ["", ""]  # Initialize with 2 sections
        section_content = []

        if not rest_content:
            rest_content = header_info
            oneSection = True
        else:
            oneSection = False

        listStart = False
        bulletListStart = False
        for line in rest_content.splitlines():
            line = line.strip()
            if not line:
                continue
            if key_value_pattern.match(line):
                key, value = key_value_pattern.match(line).groups()
                section_content.append(f'<span class="list-key"><h3>{key}:</h3></span>\n')
                section_content.append(f'{value}\n')
            elif line.startswith('- ') or line.startswith('-'):
                if not bulletListStart:
                    section_content.append('<ul>')
                    bulletListStart = True
                section_content.append(f'<li>{line[1:].strip()}</li>')
            elif re.match(r'^\d+\.\)', line) or re.match(r'^\d+\.', line):
                if bulletListStart:
                    section_content.append('</ul>')
                    bulletListStart = False
                line = re.sub(r'^\d+\.\)?', '', line).strip()
                if not listStart:
                    section_content.append('<ol>')
                    listStart = True
                section_content.append(f'<li>{line}</li>')
            else:
                if listStart:
                    section_content.append('</ol>')
                    listStart = False
                if bulletListStart:
                    section_content.append('</ul>')
                    bulletListStart = False
                section_content.append(f'{line}\n')

        if listStart:  # Close the ordered list if it's still open
            section_content.append('</ol>')
        if bulletListStart:  # Close the unordered list if it's still open
            section_content.append('</ul>')

        if oneSection is False:
            sections[0] = header_info.strip()
            sections[1] = "\n".join(section_content) if section_content else ""
        else:
            sections[0] = "".join(section_content)

        return sections

    def post_process_html(self, html_content, dir, file_name):
        html_content = html_content.replace("default", "")
        dir_path, dir_name = os.path.split(dir)
        dir_name = dir_name.split(' ', 1)[1]

        if os.path.basename(file_name) == "index.txt":
            html_content = html_content.replace("Index", dir_name)
        return html_content

#FAQs
class FaqsRuleSet:

    def extract_content_sections(self, content, file_name):

        def preprocess_newlines(text):
            return re.sub(r'\n(?!\n|\d|-)', ' ', text)

        def parse_lists(lines):
            result = []
            listStart = False
            bulletListStart = False
            for line in lines:
                line = line.strip()
                if not line:
                    continue
                if line.startswith('- ') or line.startswith('-'):
                    if not bulletListStart:
                        result.append('<ul>')
                        bulletListStart = True
                    result.append(f'<li>{line[1:].strip()}</li>')
                elif re.match(r'^\d+\.\)', line) or re.match(r'^\d+\.', line):
                    if bulletListStart:
                        result.append('</ul>')
                        bulletListStart = False
                    line = re.sub(r'^\d+\.\)?', '', line).strip()
                    if not listStart:
                        result.append('<ol>')
                        listStart = True
                    result.append(f'<li>{line}</li>')
                else:
                    if listStart:
                        result.append('</ol>')
                        listStart = False
                    if bulletListStart:
                        result.append('</ul>')
                        bulletListStart = False
                    result.append(f'{line}\n')

            if listStart:  # Close the ordered list if it's still open
                result.append('</ol>')
            if bulletListStart:  # Close the unordered list if it's still open
                result.append('</ul>')

            return ''.join(result)

        def parse_answer(lines, start_index):
            answer_lines = []
            i = start_index
            while i < len(lines):
                line = lines[i].strip()
                if line.startswith('Q:'):
                    break
                if line.startswith('A:'):
                    answer_lines.append(line[2:].strip())  # Skip the 'A:'
                else:
                    answer_lines.append(line)
                i += 1
            return parse_lists(answer_lines), i - 1

        def process_line(question, answer):
            return (f'<h3 class="question">Q: {question}</h3>\n'
                    f'<b class="question">A</b>: {answer}')

        # Preprocess the content first
        preprocessed_content = preprocess_newlines(content.strip())

        section_content = []
        lines = preprocessed_content.splitlines()
        i = 0
        while i < len(lines):
            line = lines[i].strip()
            if line.startswith('Q:'):
                question = line[2:].strip()
                i += 1
                if i < len(lines) and lines[i].strip().startswith('A:'):
                    answer, new_index = parse_answer(lines, i)
                    section_content.append(process_line(question, answer))
                    i = new_index
            i += 1

        sections = ["".join(section_content)]
        return sections

    def post_process_html(self, html_content, dir, file_name):
        html_content = html_content.replace("default", "")
        dir_path, dir_name = os.path.split(dir)
        dir_name = dir_name.split(' ', 1)[1]
        html_content = html_content.replace("Index", dir_name)

        return html_content

# default Rules
class defaultRuleSet(RuleSet):

    def extract_content_sections(self, content, file_name):

        # Split the content into header and rest based on -----
        header_info, *rest = re.split(r'-{4,}', content.strip())
        rest_content = rest[0] if rest else ""

        # In the header, replace single newlines with spaces (to merge lines) and double newlines with a unique separator
        header_info = re.sub(r'\n(?!\n)', ' ', header_info)
        header_info = re.sub(r'\n(?!\n)', '\n\n', header_info)

        # Extract key:value pairs and categorize them
        sections = [""]  # Initialize with 1 sections
        section_content = []

        for line in rest_content.splitlines():
            line = line.strip()
            if not line:
                continue
            section_content.append(f'{line}')

        sections[0] = header_info.strip()

        return sections

    def post_process_html(self, html_content, dir, file_name):
        html_content = html_content.replace("default", "")
        dir_path, dir_name = os.path.split(dir)
        dir_name = dir_name.split(' ', 1)[1]

        if os.path.basename(file_name) == "index.txt":
            html_content = html_content.replace("Index", dir_name)
        return html_content
    
# default Rules
class saddleRuleSet(RuleSet):

    def extract_content_sections(self, content, file_name):
        return content

    def post_process_html(self, html_content, dir, file_name):
        return html_content

class UniqueSaddlesRuleSet(RuleSet):
    def extract_content_sections(self, content, file_name):
        # Index file: render literally (unchanged behavior)
        if os.path.basename(file_name).lower() == "index.txt":
            return [html.escape(content).replace('\n', '<br>')]
        # Derive species name from filename
        base = os.path.splitext(os.path.basename(file_name))[0]
        species_default = re.sub(r'^#\d+\s', '', base).replace('-', ' ').strip().title()

        # If file is empty or only whitespace -> show "no saddles" message
        if not content.strip():
            msg = f'{species_default} has no unique saddles yet!'
            return [msg]

        saddles = self._parse_saddles(content, file_name)

        # If parsing found nothing -> also show the message
        if not saddles:
            msg = f'*{species_default}* has no unique saddles yet!'
            return [msg]

        cards = [self._render_card(s) for s in saddles]
        return ['\n'.join(cards)]

    def post_process_html(self, html_content, dir, file_name):
        css_tag = '<link rel="stylesheet" href="assets/saddle-builder.css">'
        if 'saddle-builder.css' not in html_content:
            html_content = css_tag + '\n' + html_content
        return html_content
    
    def _image_filename_from_label(self, label: str) -> str:
        # Convert e.g. "Tek Rock Drake" -> "Tek_Rock_Drake.png"
        if not label:
            return 'Saddle.png'
        name = re.sub(r'\s+', '_', label.strip())
        name = re.sub(r'[^A-Za-z0-9_]', '', name)
        name = re.sub(r'_+', '_', name)
        return f'{name}.png'

    def _parse_saddles(self, text, file_name):
        base = os.path.splitext(os.path.basename(file_name))[0]
        species_default = re.sub(r'^#\d+\s', '', base).replace('-', ' ').strip().title()

        lines = [ln.rstrip('\r') for ln in text.split('\n')]
        i, n = 0, len(lines)
        saddles = []

        def eat_blank():
            nonlocal i
            while i < n and not lines[i].strip():
                i += 1

        while i < n:
            eat_blank()
            if i >= n:
                break

            name = lines[i].strip()
            i += 1

            species = species_default
            if i < n and lines[i].strip().lower().startswith('unique saddle -'):
                after = lines[i].split('-', 1)[1].strip() if '-' in lines[i] else ''
                species = after or species_default
                i += 1

            flavor_lines = []
            while i < n and lines[i].strip() and lines[i].strip().lower() != 'unique bonuses:':
                flavor_lines.append(lines[i])
                i += 1

            if i < n and lines[i].strip().lower() == 'unique bonuses:':
                i += 1

            bonuses = []
            while i < n and lines[i].strip():
                bonuses.append(lines[i].strip())
                i += 1

            # Skip blocks that have no name and no bonuses
            if name or bonuses or flavor_lines:
                saddles.append({
                    'name': name,
                    'species': species,
                    'flavor_lines': flavor_lines,
                    'bonuses': bonuses
                })

            eat_blank()

        return saddles

    def _render_card(self, s):
        def esc(x):
            return html.escape(x or '')

        species_label = s.get('species', '')
        image_file = self._image_filename_from_label(species_label)
        icon_web = f'assets/saddles/{image_file}'
        fallback_web = 'assets/saddles/Saddle.png'

        flavor_html = '<br>'.join(esc(line) for line in (s.get('flavor_lines') or []))
        bonuses_html = ''.join(f'<li>{esc(b)}</li>' for b in (s.get('bonuses') or []))

        return f'''<div class="saddleContainer"> <div class="saddle"> <div class="title">{esc(s.get("name", ""))}</div> <div class="info-section"> <div class="image-container"> <img src="{icon_web}" alt="Saddle Icon" onerror="this.onerror=null;this.src='{fallback_web}';"> </div> <div class="text-container"> <div class="subtitle">Unique Saddle - {esc(species_label)}</div> <div class="flavor-text">{flavor_html}</div> <div class="bonuses"> <strong>Unique Bonuses:</strong> <ul>{bonuses_html}</ul> </div> </div> </div> </div> </div>'''.strip()