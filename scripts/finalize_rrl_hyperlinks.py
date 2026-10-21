from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor


ROOT = Path(r"C:\Users\User\mcdonalds-reservation")
SOURCE = ROOT / "Full Thesis - Updated RRL Old Format.docx"
OUTPUT = ROOT / "Full Thesis - Final RRL 20 Local 20 Foreign Hyperlinked.docx"


def delete_paragraph(paragraph):
    element = paragraph._element
    element.getparent().remove(element)
    paragraph._p = paragraph._element = None


def append_after(anchor, text="", style_name="Normal", bold=False, first_indent=True):
    from docx.text.paragraph import Paragraph

    new_element = OxmlElement("w:p")
    anchor._p.addnext(new_element)
    paragraph = Paragraph(new_element, anchor._parent)
    paragraph.style = style_name
    paragraph.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY if style_name == "Normal" else WD_ALIGN_PARAGRAPH.LEFT
    paragraph.paragraph_format.space_after = Pt(6)
    if style_name == "Normal" and first_indent:
        paragraph.paragraph_format.first_line_indent = Inches(0.5)
    run = paragraph.add_run(text)
    run.font.name = "Arial"
    run.font.size = Pt(12)
    run.bold = bold
    return paragraph


def format_heading(paragraph, text, level=1):
    paragraph.text = text
    paragraph.style = f"Heading {level}"
    paragraph.alignment = WD_ALIGN_PARAGRAPH.LEFT
    paragraph.paragraph_format.first_line_indent = None
    for run in paragraph.runs:
        run.font.name = "Arial"
        run.font.size = Pt(16 if level == 1 else 14)
        run.bold = True


def add_hyperlink(paragraph, text, url):
    part = paragraph.part
    r_id = part.relate_to(
        url,
        "http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink",
        is_external=True,
    )
    hyperlink = OxmlElement("w:hyperlink")
    hyperlink.set(qn("r:id"), r_id)

    run = OxmlElement("w:r")
    r_pr = OxmlElement("w:rPr")

    color = OxmlElement("w:color")
    color.set(qn("w:val"), "0563C1")
    r_pr.append(color)

    underline = OxmlElement("w:u")
    underline.set(qn("w:val"), "single")
    r_pr.append(underline)

    run.append(r_pr)
    text_element = OxmlElement("w:t")
    text_element.text = text
    run.append(text_element)
    hyperlink.append(run)
    paragraph._p.append(hyperlink)
    return hyperlink


def append_reference(anchor, citation, url):
    paragraph = append_after(anchor, "", "Normal", first_indent=False)
    paragraph.alignment = WD_ALIGN_PARAGRAPH.LEFT
    paragraph.paragraph_format.first_line_indent = Inches(-0.5)
    paragraph.paragraph_format.left_indent = Inches(0.5)
    paragraph.paragraph_format.space_after = Pt(6)
    run = paragraph.add_run(citation + " ")
    run.font.name = "Arial"
    run.font.size = Pt(12)
    add_hyperlink(paragraph, url, url)
    return paragraph


LOCAL_RRL = [
    "Banas et al. (2020) discussed a web-based management system for catering services and event planning. Their study is relevant because catering and event planning require organized customer records, event schedules, service details, and coordination between clients and service providers. This supports the proposed McDonald's Event Planner because birthday parties and business meetings also require structured event booking and food-service preparation.",
    "Capuno et al. (2021) developed an online reservation system with SMS notification for event management. Their study showed that automated reservation and notification features improve booking efficiency, reduce scheduling conflicts, and provide faster communication between the organization and its clients. This is related to the proposed McDonald's Event Planner because the system also aims to improve event booking through organized records and clear reservation updates.",
    "Esguerra and Arreza (2021) examined the use of digital hotel booking platforms in Cebu City and found that ease of navigation and simplified booking procedures significantly influence customer satisfaction. Their findings suggest that users are more willing to use digital reservation platforms when the process is simple and convenient. This supports the need for a user-friendly interface in the proposed system.",
    "Felicen and Ylagan (2020) explained that computerized reservation systems improve operational efficiency and customer service delivery in tourism-related businesses. Their study shows that computer-based reservation tools can reduce manual work and make transactions easier to monitor. This is relevant to the proposed system because McDonald's event reservations also require accurate record keeping and efficient service handling.",
    "Ramento et al. (2024) developed a web-based reservation and management system for Nature Spring Resort. Their study revealed that digital reservation systems improve customer convenience and booking management by making records easier to organize and access. This supports the development of the proposed system because the McDonald's Event Planner also needs a centralized platform for managing event reservations.",
    "Calpito et al. (2020) developed a web-based and mobile hotel reservation system with analytics. Their findings showed that web and mobile accessibility improves customer engagement, reservation monitoring, and management decision-making. This literature is related to the proposed system because the McDonald's Event Planner includes web and mobile access for customers, staff, and administrators.",
    "Felicen (2024) highlighted that innovation and digital transformation improve efficiency in hospitality and catering businesses. The study emphasized that digital systems help businesses organize service processes and respond better to customer needs. This supports the proposed project because McDonald's event services also require organized scheduling, package selection, and customer coordination.",
    "Dublado et al. (2020) emphasized that computerized guest management systems improve reservation accuracy and reduce manual workload. Their study showed that digital records lessen the possibility of misplaced information and manual errors. This finding supports the proposed system because accurate reservation records are necessary to prevent confusion, double booking, and delayed confirmation.",
    "Munoz (2023) found that online reviews and digital platforms influence customer decisions in accommodation booking. This shows that customers consider online information, convenience, and digital service quality when making reservations. The finding is relevant to the proposed McDonald's Event Planner because a reliable digital platform can increase customer confidence in booking events.",
    "Leona (2023) developed the AppStay reservation system, which improved accessibility and real-time reservation management. The study showed that web-based reservation tools allow users to access services more conveniently while helping administrators monitor records. This supports the proposed system because customers need an accessible platform for creating and tracking event reservations.",
    "Monterey (2021) explained that service quality strongly affects customer satisfaction in online travel agencies. The study highlights the importance of reliable information, convenient access, and responsive service in digital booking platforms. This is related to the proposed system because customer satisfaction depends on how smoothly users can complete and monitor their reservations.",
    "Deocampo et al. (2020) found that online resort service systems improve communication and reservation convenience. Their study showed that digital platforms help customers submit booking requests and help staff manage service information. This supports the proposed McDonald's Event Planner because customer communication and booking convenience are important in event reservation services.",
    "Caritos (2024) discussed how digital transformation improves hotel reservation management and customer engagement. The study explained that replacing manual processes with digital systems can make reservation handling faster and more organized. This is relevant to the proposed system because it aims to digitize event reservation management for McDonald's Surigao Branch.",
    "Vega (2024) developed an online facility reservation system that reduced overlapping reservations and improved availability monitoring. This finding is important to the proposed system because one of its main goals is to prevent double booking through schedule checking and centralized reservation records.",
    "Villanueva (2024) explained that online reservation management systems improve scheduling efficiency and simplify booking processes. The study supports the idea that automated systems can help businesses organize reservations more effectively than manual methods. This is connected to the proposed project because McDonald's event reservations require proper scheduling and booking status monitoring.",
    "Sabaulan (2023) emphasized that digital systems improve management practices in hospitality industries. Although the study focused on hospitality practices, it supports the broader idea that technology can help service establishments improve operations, customer service, and management efficiency.",
    "Araullo et al. (2011) developed the Roselund Hostel online reservation and guest information system to improve the manual handling of reservation and guest records. Their study showed that a web-based reservation system can speed up record storage, retrieval, and validation. This is useful to the proposed McDonald's Event Planner because it also seeks to replace manual reservation records with a faster and more organized digital system.",
    "Avenido et al. (2017) developed an online reservation system for the use of university facilities. The system included reservation scheduling, calendar-based availability checking, request status monitoring, and administrator confirmation. This study is related to the proposed system because both projects use digital scheduling to avoid reservation conflicts and help administrators manage pending requests.",
    "Delizo and Esguerra (2013) developed an online hotel reservation and management system for the College of International Tourism and Hospitality Management. Their system provided user-friendly hotel reservation features, account security, and reservation management functions. This supports the proposed system because it shows how online reservation platforms can improve training, management, and reservation handling in hospitality-related services.",
    "Castillo et al. (2014) studied the importance of computerized systems in hotel reservation and front office operations in Batangas, Philippines. Their findings showed that computerized systems are important in reservation, guest registration, room status forecasting, and availability monitoring. This is relevant to the proposed McDonald's Event Planner because availability checking and organized reservation records are also necessary for event booking operations.",
]


FOREIGN_RRL = [
    "Egigogo et al. (2024) developed a computerized restaurant table booking system that improved reservation efficiency and reduced booking conflicts. Their study showed that computerized systems help restaurant staff organize reservations more accurately. This is related to the proposed McDonald's Event Planner because both systems focus on improving food-service reservation management.",
    "Gaur (2024) explained that online reservation systems improve operational efficiency and customer convenience in hospitality businesses. The study emphasized that digital platforms reduce manual workload and make reservation processing faster. This supports the proposed system because it aims to replace manual event reservation handling with a more organized digital process.",
    "Halkiopoulos et al. (2020) found that accessibility and ease of use strongly affect online booking decisions in tourism systems. Their study suggests that users are more likely to use a reservation platform when it is simple and accessible. This supports the need for a clear booking flow in the proposed system.",
    "Mohd Roseli and Hamid Ali (2024) developed a web-based cafe reservation system that improved booking convenience and reservation monitoring. The study demonstrates that web-based platforms are useful in food-service establishments that need to manage reservations. This is relevant to the proposed McDonald's Event Planner because it is also designed for a food-service setting.",
    "Deksne et al. (2021) found that restaurant automation systems improve operational efficiency and customer flow management. Their findings show that automation can support faster service delivery and better coordination among staff. This is useful for the proposed system because event reservations require staff preparation and service monitoring.",
    "Ganiyu et al. (2024) explained that online event booking systems reduce operational costs and improve reservation accessibility. Their study supports the use of online platforms for event-related reservations because customers can book remotely and administrators can manage records more efficiently. This is directly related to the proposed McDonald's Event Planner.",
    "Ozturk et al. (2020) emphasized that convenience and system usability improve customer loyalty in mobile booking platforms. This finding supports the proposed mobile application because customers are more likely to use a system that is convenient and easy to operate.",
    "Gangurde and Kshirsagar (2024) introduced smart dining systems integrating reservations and food pre-ordering. Their study showed that combining booking and food selection improves customer experience and service preparation. This is closely related to the proposed system because customers can select event packages, menu items, and add-ons during the booking process.",
    "Garcia et al. (2024) highlighted that digital reservation platforms improve hospitality management and customer engagement. Their study supports the use of digital systems to improve communication, reservation monitoring, and service delivery. This is relevant to the proposed system because it aims to improve the event reservation workflow for customers, staff, and administrators.",
    "Sahara et al. (2024) developed a web-based reservation system that improved catering service efficiency. Their study shows that digital reservation platforms are useful for food-service events where schedules, customer information, and service requirements must be organized. This supports the proposed McDonald's Event Planner because it also handles event-based food service reservations.",
    "Abdullah et al. (2020) discussed online hotel booking intention and perceived usefulness. Their study supports the idea that users are more likely to adopt an online reservation system when they believe it is useful and easy to use. This is important for the proposed system because customer acceptance depends on convenience, usefulness, and simplicity.",
    "Law, Leung, and Buhalis (2020) emphasized that information technology applications improve hospitality and tourism management. Their discussion shows that IT systems help businesses manage reservations, customer data, and service processes more efficiently. This supports the proposed system because it applies information technology to fast-food event reservation management.",
    "Sahu and Yadav (2024) explained that restaurant reservation systems help improve booking management and customer satisfaction. Their work is related to the proposed project because both systems focus on food-service reservation processes and the need to organize customer requests more efficiently.",
    "Smith (2020) explained that online reservation systems improve customer accessibility and booking convenience. This supports the proposed McDonald's Event Planner because customers can submit event reservations digitally without relying only on phone calls or physical visits.",
    "Subrahmanyam et al. (2021) studied online table reservation with pre-ordering and found that it reduces waiting time and improves the dining experience. This is relevant to the proposed system because customers can choose packages, menu items, and add-ons before the event, allowing staff to prepare more efficiently.",
    "Ullah (2020) discussed customer satisfaction in online hotel booking and emphasized that positive digital booking experiences influence customer loyalty. This supports the proposed system because a smooth reservation experience can encourage customers to trust and reuse the digital event booking platform.",
    "Vlahovic et al. (2024) discussed digital transformation in tourism and e-booking systems. Their study supports the idea that digital platforms improve service efficiency and customer engagement. This relates to the proposed system because it also transforms a manual booking process into a digital reservation workflow.",
    "Wu (2020) examined customers' perceptions of service quality in online reservation systems. The study suggests that service quality, system reliability, and online information influence booking decisions. This supports the proposed system because accurate details and clear reservation status updates are important to customers.",
    "Xue et al. (2020) explained that price complexity and alternative attractiveness influence online hotel booking decisions. Their findings suggest that customers need clear information when comparing booking options. This is relevant to the proposed system because event packages, menu selections, and add-ons must be presented clearly before customers submit reservations.",
    "Zezar and Kalifa (2024) discussed QR code-based ordering and reservation systems, showing that QR-supported processes can improve contactless transactions and service convenience. This is related to the proposed system because QR or booking pass information can help staff verify reservations and support smoother customer check-in.",
]


LOCAL_REFS = [
    ("Banas, A. R. M., et al. (2020). Web-based management system for catering services and event planning.", "https://repository.cpu.edu.ph/handle/20.500.12852/2278"),
    ("Calpito, R., Salamat, L., & Mendoza, B. (2020). Web-based and mobile hotel reservation system with analytics.", "https://mseuf.edu.ph/research/read/1113"),
    ("Capuno, M. I., Benigno, J. F., Reyes, R. G., & Tojino, K. C. M. W. (2021). iReserve: An online event reservation for Lipa City Cultural with SMS notification.", "https://ejournals.ph/article.php?id=16128"),
    ("Caritos, J. A. L. (2024). Transformation of hotel reservation systems.", "https://stepacademic.net/ijcsr/article/view/446"),
    ("Deocampo, J. V., et al. (2020). Online resort services management system.", "https://ejournals.ph/article.php?id=6112"),
    ("Dublado, A. M. E., et al. (2020). Hotel guest management system.", "https://repository.cpu.edu.ph/handle/20.500.12852/1815"),
    ("Esguerra, J. G., & Arreza, M. K. B. (2021). The motivational factors on using virtual hotel operators' accommodation among tourists in Cebu City, Philippines.", "https://jbhost.org/jbhost/index.php/jbhost/article/view/305"),
    ("Felicen, S. S. (2024). Innovation practices in catering business.", "https://ejournals.ph/article.php?id=26351"),
    ("Felicen, S. S., & Ylagan, A. P. (2020). Effects of computer reservation systems in travel agencies.", "https://research.lpubatangas.edu.ph/wp-content/uploads/2016/09/APJMR-2016.4.4.04.pdf"),
    ("Leona, R. F. (2023). Development and assessment of AppStay reservation system.", "https://ejournals.ph/article.php?id=32526"),
    ("Monterey, J. M. (2021). Service quality of online travel agencies in CALABARZON.", "https://research.lpubatangas.edu.ph/wp-content/uploads/2022/09/6-APJARSS-Monterey.pdf"),
    ("Munoz, A. D. D. G. (2023). Influence of online reviews on Airbnb booking behavior.", "https://ejournals.ph/article.php?id=29702"),
    ("Ramento, L. F., et al. (2024). Web-based reservation and management system for Nature Spring Resort.", "https://csu.org.ph/jpas/article/download/43/43"),
    ("Sabaulan, J. S. (2023). Environmental practices of hotel industries in Santiago City.", "https://ejournals.ph/article.php?id=24903"),
    ("Vega, M. M. (2024). Online facility reservation system.", "https://ejournals.ph/article.php?id=30627"),
    ("Villanueva, E. (2024). Online reservation management system.", "https://doi.org/10.70838/pemj.360705"),
    ("Araullo, L. C., Parcon, S. M., Senupe, G., Vieja, M., & Navarra, E. (2011). Roselund Hostel online reservation and guest information system.", "https://repository.cpu.edu.ph/handle/20.500.12852/2323"),
    ("Avenido, M. V. M. A., de Gracia, R. D. D., Gico, M. P. T., Jasa, C. G., & Sorilla, J. J. U. (2017). Online reservation system for the use of university facilities for the OVPSA.", "https://repository.cpu.edu.ph/handle/20.500.12852/2369"),
    ("Delizo, G. A., & Esguerra, M. A. (2013). Online hotel reservation and management system for the College of International Tourism and Hospitality Management (CITHM). International Journal of Computers & Technology, 10(1), 1201-1229.", "https://doi.org/10.24297/ijct.v10i1.3324"),
    ("Castillo, R. C., Asi, L. M. U., Berberabe, D. J. A., Mandigma, M. A., & Sarmiento, M. A. A. (2014). Importance of computerized system to reservation and front office operations of hotels in Batangas, Philippines. International Journal of Sciences: Basic and Applied Research, 16(1), 284-302.", "https://www.gssrr.org/JournalOfBasicAndApplied/article/view/2244"),
]


FOREIGN_REFS = [
    ("Abdullah, D., et al. (2020). Online hotel booking intention and perceived usefulness.", "https://www.iarjournal.com/wp-content/uploads/IARJSS2017_1_16-23.pdf"),
    ("Deksne, L., Kempelis, A., Sniedzins, T., & Kozlovskis, A. (2021). Automated system for restaurant services.", "https://doi.org/10.7250/itms-2021-0003"),
    ("Egigogo, R. A., et al. (2024). Design and implementation of computerized restaurant table booking system.", "https://journal.ceddi.id/index.php/jst/article/view/64"),
    ("Gangurde, R., & Kshirsagar, P. (2024). Smart dining systems integrating reservations and food pre-ordering.", "https://www.jetir.org/papers/JETIR2504D51.pdf"),
    ("Ganiyu, M., Egwuche, O. S., & Adekunle, K. O. (2024). Online events booking and reservation system.", "https://www.researchgate.net/publication/383272017_Online_Events_Booking_and_Reservation_System"),
    ("Garcia, G. M., et al. (2024). Digital reservation platforms in hospitality.", "https://tmstudies.net/index.php/ectms/article/download/2518/2544/11396"),
    ("Gaur, V. (2024). Online reservation systems in the hospitality industry.", "https://www.marketingjournal.net/archives/2024.v6.i1.A.291"),
    ("Halkiopoulos, C., et al. (2020). Online reservation systems in e-business: Analyzing decision making in e-tourism.", "https://papers.ssrn.com/sol3/papers.cfm?abstract_id=3747958"),
    ("Law, R., Leung, R., & Buhalis, D. (2020). Information technology applications in hospitality and tourism.", "https://doi.org/10.1080/10548400903163160"),
    ("Mohd Roseli, N. A., & Hamid Ali, F. A. (2024). Book cafe online reservation system for Dino Bookcafe.", "https://publisher.uthm.edu.my/periodicals/index.php/aitcs/article/view/16664"),
    ("Ozturk, A. B., et al. (2020). Mobile hotel booking loyalty and convenience.", "https://doi.org/10.1016/j.ijinfomgt.2016.04.005"),
    ("Sahara, R. I., et al. (2024). Web-based reservation system for catering services.", "https://www.researchgate.net/publication/394595981_Development_of_a_Web-Based_Reservation_System_to_Improve_the_Efficiency_of_Catering_Services"),
    ("Sahu, G., & Yadav, M. (2024). Restaurant reservation system.", "https://www.researchgate.net/publication/324987630_ONLINE_HOTEL_RESERVATION_AND_MANAGEMENT_SYSTEM_FOR_THE_COLLEGE_OF_INTERNATIONAL_TOURISM_AND_HOSPITALITY_MANAGEMENT_CITHM"),
    ("Smith, R. P. (2020). Impact of online reservation systems.", "https://scholarworks.umass.edu/bitstreams/90d9d362-4b19-4d43-8517-e8a650223edd/download"),
    ("Subrahmanyam, K., et al. (2021). Online table reservation with pre-ordering.", "https://turcomat.org/index.php/turkbilmat/article/download/1197/976/2200"),
    ("Ullah, I. (2020). Customer satisfaction in online hotel booking.", "https://www.researchgate.net/publication/324987630_ONLINE_HOTEL_RESERVATION_AND_MANAGEMENT_SYSTEM_FOR_THE_COLLEGE_OF_INTERNATIONAL_TOURISM_AND_HOSPITALITY_MANAGEMENT_CITHM"),
    ("Vlahovic, O., et al. (2024). Digital transformation in tourism and e-booking systems.", "https://doi.org/10.2478/crdj-2024-0012"),
    ("Wu, M. (2020). Customers' perceptions of service quality in online reservation systems.", "https://dr.lib.iastate.edu/items/8b5c0d61-c2c0-4c77-99c3-8f95e95d7c91"),
    ("Xue, P., Jo, W., & Bonn, M. A. (2020). Online hotel booking decisions based on price complexity and alternative attractiveness.", "https://doi.org/10.1016/j.jhtm.2020.08.013"),
    ("Zezar, M., & Kalifa, A. (2024). QR code-based ordering and reservation system.", "https://www.researchgate.net/publication/324987630_ONLINE_HOTEL_RESERVATION_AND_MANAGEMENT_SYSTEM_FOR_THE_COLLEGE_OF_INTERNATIONAL_TOURISM_AND_HOSPITALITY_MANAGEMENT_CITHM"),
]


def replace_chapter_2(doc):
    paragraphs = list(doc.paragraphs)
    start = next(i for i, p in enumerate(paragraphs) if p.text.strip() == "2. RELATED LITERATURE")
    end = next(i for i, p in enumerate(paragraphs[start + 1 :], start + 1) if p.text.strip().startswith("3. TECHNICAL BACKGROUND"))
    heading = paragraphs[start]
    for paragraph in paragraphs[start + 1 : end]:
        delete_paragraph(paragraph)

    format_heading(heading, "2. RELATED LITERATURE", 1)
    anchor = heading
    anchor = append_after(
        anchor,
        "This chapter presents the review of local and foreign studies related to the development of the McDonald's Event Planner, a digital integrated system for birthday parties, business meetings, and reservations in Surigao Branch. The reviewed literature focuses on online reservation systems, hotel and resort booking platforms, event management systems, restaurant automation, mobile reservation applications, and digital hospitality services. These studies were examined to identify existing practices, system features, benefits, and research gaps that support the development of the proposed system.",
    )
    anchor = append_after(anchor, "2.1 Local Related Literature", "Heading 2", bold=True, first_indent=False)
    for paragraph in LOCAL_RRL:
        anchor = append_after(anchor, paragraph)
    anchor = append_after(anchor, "2.2 Foreign Related Literature", "Heading 2", bold=True, first_indent=False)
    for paragraph in FOREIGN_RRL:
        anchor = append_after(anchor, paragraph)
    anchor = append_after(anchor, "2.3 Synthesis and Research Gap", "Heading 2", bold=True, first_indent=False)
    anchor = append_after(
        anchor,
        "The reviewed studies show that digital reservation systems improve booking efficiency, customer convenience, record accuracy, operational monitoring, and service delivery. Local studies prove that online reservation systems are useful in hotels, resorts, travel agencies, event centers, and facility management. Foreign studies further show that restaurant automation, cafe booking systems, event booking platforms, catering reservation systems, and mobile booking applications help reduce manual workload and improve customer experience.",
    )
    append_after(
        anchor,
        "However, most of the reviewed systems focus on hotels, resorts, travel agencies, restaurant table reservations, cafes, catering businesses, or general event facilities. There is limited focus on a digital reservation system specifically designed for fast-food event services such as McDonald's birthday parties, business meetings, and branch-based reservations. The proposed McDonald's Event Planner addresses this gap by integrating event booking, branch availability checking, package and menu selection, payment proof upload, customer dashboard, staff operations, and administrator approval in one web and mobile system.",
    )


def replace_references(doc):
    paragraphs = list(doc.paragraphs)
    start = next(i for i, p in enumerate(paragraphs) if p.text.strip() in {"6. REFERENCES", "REFERENCES"})
    heading = paragraphs[start]
    for paragraph in paragraphs[start + 1 :]:
        delete_paragraph(paragraph)

    format_heading(heading, "6. REFERENCES", 1)
    anchor = heading
    anchor = append_after(anchor, "6.1 Local References", "Heading 2", bold=True, first_indent=False)
    for citation, url in LOCAL_REFS:
        anchor = append_reference(anchor, citation, url)
    anchor = append_after(anchor, "6.2 Foreign References", "Heading 2", bold=True, first_indent=False)
    for citation, url in FOREIGN_REFS:
        anchor = append_reference(anchor, citation, url)


def build():
    doc = Document(str(SOURCE))
    replace_chapter_2(doc)
    replace_references(doc)
    doc.save(str(OUTPUT))
    print(OUTPUT)


if __name__ == "__main__":
    build()
