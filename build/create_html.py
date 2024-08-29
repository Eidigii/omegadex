import re
import os
import json
from lib.Rules.rules import RuleSet, VariantsRuleSet, listRuleSet, defaultRuleSet, DinosRuleSet, BossesRuleSet, FaqsRuleSet

# Define the HTML template
template = """<h1><span class="[OmegaClass]" style="background-color: rgba(211, 211, 211, 0.5); padding: 5px; border-radius: 10px;">[Title]</span></h1>
<div style="background-color: rgba(111, 111, 111, 0.6); padding: 10px; border-radius: 10px; display: inline-block;">[Sections]
</div>"""

# Function to create a dynamic HTML template
def create_dynamic_template(num_sections, header_map, span_class):
    sections_template = ""
    for i in range(num_sections):
        header_size = header_map.get(str(i + 1), None)  # Default to no header if not specified
        section_header = f"<h{header_size}>Section {i+1}</h{header_size}>" if header_size else ""
        section_content = f"<span class=\"section{i+1}\" style=\"\">[Section {i+1} Content]</span>"
        sections_template += f"{section_header}\n{section_content}<hr>"
    return template.replace("[OmegaClass]", span_class).replace("[Sections]", sections_template)

# Function to generate HTML from text file content
def generate_html(file_path, dir, rule_set, template):
    # Extract the title from the filename

    # Get the base filename without the extension
    base_filename = os.path.splitext(os.path.basename(file_path))[0]

    # Use a regular expression to remove <number #> from the filename
    title = re.sub(r'^#\d+\s', '', base_filename)

    # Replace hyphens with spaces and convert to title case
    title = title.replace("-", " ").title()

    # Read content from the text file
    with open(file_path, 'r') as file:
        content = file.read()

    # Extract sections of the content using the provided rule set
    file_name = os.path.basename(file_path)
    sections = rule_set.extract_content_sections(content, file_name)

    # Populate the template
    html_content = template.replace("[Title]", title)
    
    for i, section_content in enumerate(sections):
        if section_content.strip():  # Check if section_content is not empty
            html_content = html_content.replace(f"[Section {i + 1} Content]", section_content)

    # Remove unused section headers
    html_content = re.sub(r'(?:<h\d>Section \d+</h\d>\n)?<span class="section\d+" style="">\[Section \d+ Content\]</span><hr>', '', html_content)
    
    # Remove unused sections if they exist
    html_content = re.sub(r'<h\d>Section \d+</h\d>', '', html_content)

    # Post-process HTML with rule set specific logic
    html_content = rule_set.post_process_html(html_content, dir, file_name)

    # Write the populated template to a new HTML file
    output_file = os.path.join(os.path.dirname(file_path), f"{os.path.splitext(os.path.basename(file_path))[0]}")
    with open(output_file, 'w') as file:
        file.write(html_content)

    return output_file

def process_txt(dir_path, config_file, rule_set_class):
    # Load the configuration from the JSON file
    with open(config_file, 'r') as f:
        config = json.load(f)
    
    num_sections = config.get("num_sections")
    header_map = config.get("header_map")
    span_class = config.get("span_class")

    rule_set = rule_set_class()
    for file in os.listdir(dir_path):
        if file.endswith('.txt') and not file == 'additional.txt':
            if os.path.basename(file) == "index.txt":
                if dir_path.endswith("#4 Variants"):
                    template = create_dynamic_template(3, header_map, span_class)
                elif "#4 Variants" in dir_path:
                    template = create_dynamic_template(1, header_map, span_class)
                else:
                    template = create_dynamic_template(num_sections, header_map, span_class)
            else:
                template = create_dynamic_template(num_sections, header_map, span_class)
            file_path = os.path.join(dir_path, file)
            output_html = generate_html(file_path, dir_path, rule_set, template)
            print(f"Generated HTML file: {output_html}")

# Define the base directory containing all Data dirs
base_dir = os.path.join(os.path.dirname(__file__), '../Data')

# Define a dictionary to map directory names to classes and config files
dir_class_map = {
    "#1 Welcome": (listRuleSet, 'list_config.json'),
    "#2 Getting Started": (listRuleSet, 'list_config.json'),
    "#3 Progression Guide": (listRuleSet, 'list_config.json'),
    "#4 Variants": (VariantsRuleSet, 'variants_config.json'),
    "#5 Dinos": (DinosRuleSet, 'dinos_config.json'),
    "#6 Equipment": (listRuleSet, 'list_config.json'),
    "#7 Bosses": (BossesRuleSet, 'bosses_config.json'),
    "#8 Items": (listRuleSet, 'list_config.json'),
    "#10 Mating": (listRuleSet, 'list_config.json'),
    "#11 Paragons": (listRuleSet, 'list_config.json'),
    "#12 NPCs": (listRuleSet, 'list_config.json'),
    "#13 FAQs": (FaqsRuleSet, 'faqs_config.json'),
    "#14 Links": (defaultRuleSet, 'default_config.json'),
    "#15 Changelog": (listRuleSet, 'list_config.json'),
    # Add more directories and corresponding classes as needed
}

# Walk through the base directory
for root, dirs, files in os.walk(base_dir):
    dir_path_parts = root.split(os.sep)
    for dir in reversed(dir_path_parts):
        if dir == "#9 Uniques":  # Skip directory "#9"
            break
        if dir in dir_class_map:
            rule_set_class, config_file_name = dir_class_map.get(dir, (defaultRuleSet, 'default_config.json'))  # Get the class and config file for the directory, or defaults if not found
            config_file = os.path.join(os.path.dirname(__file__), 'config', config_file_name)
            try:
                process_txt(root, config_file, rule_set_class)
            except Exception as e:
                print(f"Error processing directory {root}: {e}")
            break  # Stop the loop after finding a match
