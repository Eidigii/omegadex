import re
import os
import json
import subprocess 
import sys        

try:
    from lib.Rules.rules import RuleSet, VariantsRuleSet, listRuleSet, defaultRuleSet, DinosRuleSet, BossesRuleSet, FaqsRuleSet, saddleRuleSet
except ImportError:
    current_script_path = os.path.dirname(os.path.abspath(__file__))
    project_root_path = os.path.abspath(os.path.join(current_script_path, '..'))
    if project_root_path not in sys.path: sys.path.insert(0, project_root_path)
    from lib.Rules.rules import RuleSet, VariantsRuleSet, listRuleSet, defaultRuleSet, DinosRuleSet, BossesRuleSet, FaqsRuleSet, saddleRuleSet

template_g = """<h1><span class="[OmegaClass]" style="background-color: rgba(211, 211, 211, 0.5); padding: 5px; border-radius: 10px;">[Title]</span></h1>
<div style="background-color: rgba(111, 111, 111, 0.6); padding: 10px; border-radius: 10px; display: inline-block;">[Sections]
</div>"""

def create_dynamic_template(num_sections, header_map, span_class):
    sections_template = ""
    for i in range(num_sections):
        header_size = header_map.get(str(i + 1), None) 
        section_header = f"<h{header_size}>Section {i+1}</h{header_size}>" if header_size else ""
        section_content = f"<span class=\"section{i+1}\" style=\"\">[Section {i+1} Content]</span>"
        sections_template += f"{section_header}\n{section_content}<hr>"
    return template_g.replace("[OmegaClass]", span_class).replace("[Sections]", sections_template)

def generate_html(file_path, dir_param, rule_set_instance, template_param): 
    base_filename = os.path.splitext(os.path.basename(file_path))[0] 
    title = re.sub(r'^#\d+\s', '', base_filename)
    title = title.replace("-", " ").title()

    with open(file_path, 'r', encoding='utf-8') as file: 
        content = file.read()
    file_name_for_rules = os.path.basename(file_path)
    
    sections = []
    try:
        sections = rule_set_instance.extract_content_sections(content, file_name_for_rules)
    except IndexError as e_extract:
        print(f"    ERROR IN {type(rule_set_instance).__name__}.extract_content_sections for {file_name_for_rules} in {dir_param}: {e_extract}")
        # import traceback; traceback.print_exc() # Uncomment for full traceback if needed
        return None 
    except Exception as e_extract_other:
        print(f"    UNEXPECTED ERROR IN {type(rule_set_instance).__name__}.extract_content_sections for {file_name_for_rules} in {dir_param}: {e_extract_other}")
        # import traceback; traceback.print_exc()
        return None

    html_content = template_param.replace("[Title]", title) 
    
    for i, section_content_item in enumerate(sections): 
        if section_content_item is not None and section_content_item.strip(): 
            html_content = html_content.replace(f"[Section {i + 1} Content]", section_content_item)

    html_content = re.sub(r'(?:<h\d>Section \d+</h\d>\n)?<span class="section\d+" style="">\[Section \d+ Content\]</span><hr>', '', html_content, flags=re.DOTALL)
    html_content = re.sub(r'<h\d>Section \d+</h\d>', '', html_content, flags=re.DOTALL)

    final_html_output = ""
    try:
        if dir_param.endswith("#14 Saddle Creator"): 
            final_html_output = rule_set_instance.post_process_html(content, dir_param, file_name_for_rules)
        else:
            final_html_output = rule_set_instance.post_process_html(html_content, dir_param, file_name_for_rules)
    except IndexError as e_post:
        print(f"    ERROR IN {type(rule_set_instance).__name__}.post_process_html for {file_name_for_rules} in {dir_param}: {e_post}")
        # import traceback; traceback.print_exc()
        return None
    except Exception as e_post_other:
        print(f"    UNEXPECTED ERROR IN {type(rule_set_instance).__name__}.post_process_html for {file_name_for_rules} in {dir_param}: {e_post_other}")
        # import traceback; traceback.print_exc()
        return None

    output_file = os.path.join(os.path.dirname(file_path), base_filename) 
    with open(output_file, 'w', encoding='utf-8') as file: 
        file.write(final_html_output)
    return output_file

def process_txt(dir_path, config_file, rule_set_class_ref): 
    with open(config_file, 'r', encoding='utf-8') as f: 
        config = json.load(f)
    
    num_sections_from_config = config.get("num_sections")
    if num_sections_from_config is None: num_sections_from_config = 1 

    header_map_from_config = config.get("header_map", {}) 
    span_class_from_config = config.get("span_class", "default-omega-class") 

    rule_set_instance_obj = rule_set_class_ref() 
    
    for file_iter_loop in os.listdir(dir_path): 
        if file_iter_loop.endswith('.txt') and not file_iter_loop == 'additional.txt' and not file_iter_loop.startswith('_'):
            effective_num_sections = num_sections_from_config 
            current_file_basename_loop = os.path.basename(file_iter_loop)

            if rule_set_class_ref == VariantsRuleSet:
                if current_file_basename_loop == "index.txt":
                    effective_num_sections = 3
                else:
                    effective_num_sections = 4
            
            template_for_this_file_loop = create_dynamic_template(effective_num_sections, header_map_from_config, span_class_from_config)
            
            file_path_to_process_loop = os.path.join(dir_path, file_iter_loop)
            output_html_result = generate_html(file_path_to_process_loop, dir_path, rule_set_instance_obj, template_for_this_file_loop)
            
            if output_html_result:
                 # print(f"Generated HTML file: {os.path.basename(output_html_result)}") # Keep console cleaner by default
                 pass


base_dir = os.path.join(os.path.dirname(__file__), '../Data')
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
    "#13 Egg Chart": (listRuleSet, 'list_config.json'),
    "#14 Saddle Creator": (saddleRuleSet, 'default_config.json'), 
    "#15 FAQs": (FaqsRuleSet, 'faqs_config.json'),
    "#16 Links": (defaultRuleSet, 'default_config.json'), 
    "#17 Changelog": (listRuleSet, 'list_config.json'), 
}

if __name__ == "__main__":
    print("Starting HTML file generation...")
    
    for root, dirs, files_in_loop_main_walk in os.walk(base_dir): 
        if "#9 Uniques" in dirs: dirs.remove("#9 Uniques")  
        dir_path_parts_walk = root.split(os.sep)
        rule_class_for_current_root_walk = defaultRuleSet 
        config_filename_for_current_root_walk = 'default_config.json' 
        for dir_segment_from_path_walk in reversed(dir_path_parts_walk):
            if dir_segment_from_path_walk == "#9 Uniques": break
            if dir_segment_from_path_walk in dir_class_map:
                rule_class_for_current_root_walk, config_filename_for_current_root_walk = dir_class_map[dir_segment_from_path_walk]
                break 
        config_file_full_path_walk = os.path.join(os.path.dirname(__file__), 'config', config_filename_for_current_root_walk)
        if not os.path.exists(config_file_full_path_walk):
            print(f"  CONFIG FILE MISSING: {config_file_full_path_walk} for rule '{rule_class_for_current_root_walk.__name__}'. Using defaults.")
            config_file_full_path_walk = os.path.join(os.path.dirname(__file__), 'config', 'default_config.json')
            rule_class_for_current_root_walk = defaultRuleSet
        try:
            process_txt(root, config_file_full_path_walk, rule_class_for_current_root_walk) 
        except Exception as e_walk:
            print(f"  Error processing directory {root} with rule class {rule_class_for_current_root_walk.__name__}: {e_walk}")

    print("HTML file generation completed.")

    print("\nStarting search index generation...")
    script_location_dir_main = os.path.dirname(os.path.abspath(__file__)) 
    try:
        search_indexer_script_path_main = os.path.join(script_location_dir_main, 'create_search_index.py')
        if not os.path.exists(search_indexer_script_path_main): print(f"Error: Search indexer script not found: {search_indexer_script_path_main}")
        else:
            python_executable_main = sys.executable
            result_main = subprocess.run( [python_executable_main, search_indexer_script_path_main], capture_output=True, text=True, check=False, cwd=script_location_dir_main )
            if result_main.stdout: print(f"Output from search indexer:\n{result_main.stdout.strip()}")
            if result_main.stderr: print(f"Errors from search indexer:\n{result_main.stderr.strip()}")
            if result_main.returncode == 0: print("Search index generation completed successfully.")
            else: print(f"Search index generation FAILED with exit code {result_main.returncode}.")
    except Exception as e_search_main: print(f"An error occurred while trying to run search indexer: {e_search_main}")
    print("\nBuild process finished.")